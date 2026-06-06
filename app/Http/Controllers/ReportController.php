<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Reportes',      only: ['index', 'show', 'clientTickets', 'pdf']),
            new Middleware('can:Crear Reportes',    only: ['create', 'store']),
            new Middleware('can:Editar Reportes',   only: ['edit', 'update']),
            new Middleware('can:Eliminar Reportes', only: ['destroy']),
        ];
    }

    public function index()
    {
        $reports = Report::with(['client', 'creator'])
            ->withCount('tickets')
            ->latest()
            ->get()
            ->map(function ($r) {
                $r->period_label = $r->period_label;
                return $r;
            });

        $clients = Client::orderBy('business_name')->get(['id', 'business_name']);

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'clients' => $clients,
        ]);
    }

    public function create()
    {
        $clients = Client::with(['services' => fn($q) => $q->where('status', 'active')])
            ->orderBy('business_name')
            ->get(['id', 'business_name']);

        return Inertia::render('Reports/Create', [
            'clients' => $clients,
        ]);
    }

    /** Return tickets for a given client filtered by date range (used for preview & auto-include) */
    public function clientTickets(Client $client, Request $request)
    {
        $query = Ticket::withTrashed()
            ->where('client_id', $client->id)
            ->with(['clientService', 'assigned']);

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->from . ' 00:00:00';
            $to   = $request->to   . ' 23:59:59';

            $query->where(function ($q) use ($from, $to) {
                // Support tickets: filtra por created_at
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where('source_type', '!=', Ticket::SOURCE_RECURRING)
                          ->whereBetween('created_at', [$from, $to]);
                })
                // Recurring: filtra por el period_start del ciclo de facturación
                ->orWhere(function ($inner) use ($from, $to) {
                    $inner->where('source_type', Ticket::SOURCE_RECURRING)
                          ->whereHas('billingCycle', fn ($bc) => $bc->whereBetween('period_start', [$from, $to]));
                });
            });
        }

        $tickets = $query->latest()
            ->get(['id', 'title', 'status', 'priority', 'created_at', 'source_type', 'client_service_id', 'assigned_id', 'deleted_at']);

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'                    => 'required|exists:clients,id',
            'title'                        => 'required|string|max:255',
            'period_month'                 => 'required|integer|between:1,12',
            'period_year'                  => 'required|integer|min:2020|max:2100',
            'summary'                      => 'nullable|string',
            'notes'                        => 'nullable|string',
            'date_from'                    => 'required|date',
            'date_to'                      => 'required|date|after_or_equal:date_from',
            'pdf_options'                  => 'nullable|array',
            'pdf_options.show_assigned'    => 'boolean',
            'pdf_options.show_dates'       => 'boolean',
            'pdf_options.show_duration'    => 'boolean',
            'pdf_options.show_status_log'  => 'boolean',
        ]);

        $ticketIds = $this->resolveTicketIds($request->client_id, $request->date_from, $request->date_to);

        $report = Report::create([
            'client_id'    => $request->client_id,
            'created_by'   => Auth::id(),
            'title'        => $request->title,
            'period_month' => $request->period_month,
            'period_year'  => $request->period_year,
            'summary'      => $request->summary,
            'notes'        => $request->notes,
            'pdf_options'  => $request->input('pdf_options', []),
        ]);

        if (!empty($ticketIds)) {
            $report->tickets()->sync($ticketIds);
        }

        // Persist a self-contained snapshot of all ticket data
        $report->tickets_snapshot = $this->buildTicketsSnapshot($ticketIds);
        $report->save();

        return redirect()->route('reports.show', $report)->with('success', 'Reporte creado correctamente.');
    }

    public function show(Report $report)
    {
        $report->load(['client.assets', 'creator']);
        $report->period_label = $report->period_label;

        // Use the self-contained snapshot so deleted tickets still appear
        $tickets = collect($report->tickets_snapshot ?? [])
            ->values()
            ->toArray();

        return Inertia::render('Reports/Show', [
            'report'       => $report,
            'tickets'      => $tickets,
            'clientAssets' => $report->client?->assets?->values()->toArray() ?? [],
        ]);
    }

    public function edit(Report $report)
    {
        $report->load(['client']);

        return Inertia::render('Reports/Edit', [
            'report'  => $report,
            'clients' => Client::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function update(Request $request, Report $report)
    {
        $request->validate([
            'title'                        => 'required|string|max:255',
            'period_month'                 => 'required|integer|between:1,12',
            'period_year'                  => 'required|integer|min:2020|max:2100',
            'summary'                      => 'nullable|string',
            'notes'                        => 'nullable|string',
            'date_from'                    => 'required|date',
            'date_to'                      => 'required|date|after_or_equal:date_from',
            'pdf_options'                  => 'nullable|array',
            'pdf_options.show_assigned'    => 'boolean',
            'pdf_options.show_dates'       => 'boolean',
            'pdf_options.show_duration'    => 'boolean',
            'pdf_options.show_status_log'  => 'boolean',
        ]);

        $report->update([
            'title'        => $request->title,
            'period_month' => $request->period_month,
            'period_year'  => $request->period_year,
            'summary'      => $request->summary,
            'notes'        => $request->notes,
            'pdf_options'  => $request->input('pdf_options', []),
        ]);

        $ticketIds = $this->resolveTicketIds($report->client_id, $request->date_from, $request->date_to);

        $report->tickets()->sync($ticketIds);

        // Rebuild snapshot with the new date range
        $report->tickets_snapshot = $this->buildTicketsSnapshot($ticketIds);
        $report->save();

        return redirect()->route('reports.show', $report)->with('success', 'Reporte actualizado.');
    }

    public function destroy(Report $report)
    {
        $report->delete();
        return redirect()->route('reports.index')->with('success', 'Reporte eliminado.');
    }

    public function pdf(Report $report)
    {
        $report->load(['client.assets', 'creator']);
        $report->period_label = $report->period_label;

        // Use snapshot so the PDF is consistent even if tickets were deleted
        $tickets = collect($report->tickets_snapshot ?? [])->values()->toArray();

        $settings = Setting::pluck('value', 'key')->toArray();

        $pdf = Pdf::loadView('reports.pdf', [
            'report'       => $report,
            'tickets'      => $tickets,
            'clientAssets' => $report->client?->assets?->values()->toArray() ?? [],
            'settings'     => $settings,
            'options'      => $report->pdf_options_resolved,
        ])
        ->setPaper('letter', 'portrait')
        ->setOption('defaultFont', 'sans-serif')
        ->setOption('isHtml5ParserEnabled', true);

        $filename = 'reporte-' . $report->client->business_name . '-' . $report->period_label . '.pdf';
        $filename = str_replace(' ', '-', strtolower($filename));

        return $pdf->download($filename);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Collect ticket IDs for a client within a date range.
     * - Support tickets: matched by created_at.
     * - Recurring tickets: matched by billing_cycle.period_start so that tickets
     *   auto-created when a cycle is opened (their created_at may differ from the
     *   cycle period) always appear in the correct monthly report.
     */
    private function resolveTicketIds(int $clientId, string $from, string $to): array
    {
        $dateFrom = $from . ' 00:00:00';
        $dateTo   = $to   . ' 23:59:59';

        return Ticket::where('client_id', $clientId)
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->where(function ($inner) use ($dateFrom, $dateTo) {
                    $inner->where('source_type', '!=', Ticket::SOURCE_RECURRING)
                          ->whereBetween('created_at', [$dateFrom, $dateTo]);
                })
                ->orWhere(function ($inner) use ($dateFrom, $dateTo) {
                    $inner->where('source_type', Ticket::SOURCE_RECURRING)
                          ->whereHas('billingCycle', fn ($bc) => $bc->whereBetween('period_start', [$dateFrom, $dateTo]));
                });
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * Build a self-contained array of ticket data for the snapshot.
     * Uses withTrashed() so tickets in the trash are also captured.
     */
    private function buildTicketsSnapshot(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        // Load system messages (status log, user_id = null) per ticket
        $systemMessages = DB::table('ticket_messages')
            ->whereNull('user_id')
            ->whereIn('ticket_id', $ticketIds)
            ->orderBy('created_at')
            ->get(['ticket_id', 'message', 'created_at'])
            ->groupBy('ticket_id');

        return Ticket::withTrashed()
            ->whereIn('id', $ticketIds)
            ->with([
                'clientService:id,service_name',
                'assigned:id,name',
                'creator:id,name',
                'canvasItems.children',
                'canvasItems.uploader:id,name',
                'canvasItems.pins.user:id,name',
                'canvasItems.children.pins.user:id,name',
            ])
            ->get()
            ->map(function ($t) use ($systemMessages) {
                $log = $systemMessages->get($t->id) ?? collect();

                // Fallback start: work_started_at → created_at
                $startedAt = $t->work_started_at ?? $t->created_at;

                // Fallback end: work_finished_at → first "Completados" log entry
                $finishedAt = $t->work_finished_at;
                if (!$finishedAt) {
                    $completedEntry = $log->first(fn($m) => str_contains($m->message, 'Completados'));
                    $finishedAt = $completedEntry?->created_at ?? null;
                }

                return [
                    'id'               => $t->id,
                    'title'            => $t->title,
                    'status'           => $t->status,
                    'priority'         => $t->priority,
                    'content'          => $t->content,
                    'due_date'         => $t->due_date,
                    'created_at'       => $t->created_at,
                    'work_started_at'  => $startedAt,
                    'work_finished_at' => $finishedAt,
                    'assigned'         => $t->assigned ? ['id' => $t->assigned->id, 'name' => $t->assigned->name] : null,
                    'creator'          => $t->creator  ? ['id' => $t->creator->id,  'name' => $t->creator->name]  : null,
                    'client_service'   => $t->clientService ? ['id' => $t->clientService->id, 'service_name' => $t->clientService->service_name] : null,
                    'status_log'       => $log->map(fn($m) => [
                        'message'    => $m->message,
                        'created_at' => $m->created_at,
                    ])->values()->toArray(),
                    'canvas_items'     => $t->canvasItems->map(fn($it) => $this->serializeCanvasItem($it))->values()->toArray(),
                ];
            })
            ->toArray();
    }

    private function serializeCanvasItem($it): array
    {
        return [
            'id'              => $it->id,
            'type'            => $it->type,
            'file_path'       => $it->file_path,
            'file_name'       => $it->file_name,
            'mime'            => $it->mime,
            'url'             => $it->url,
            'caption'         => $it->caption,
            'approval_status' => $it->approval_status,
            'approval_note'   => $it->approval_note,
            'position'        => $it->position,
            'stack_position'  => $it->stack_position,
            'uploader'        => $it->uploader ? ['id' => $it->uploader->id, 'name' => $it->uploader->name] : null,
            'pins'            => $it->pins?->map(fn($p) => [
                'id'       => $p->id,
                'x_pct'    => $p->x_pct,
                'y_pct'    => $p->y_pct,
                'comment'  => $p->comment,
                'resolved' => $p->resolved,
                'user'     => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null,
            ])->values()->toArray() ?? [],
            'children'        => $it->children?->map(fn($c) => $this->serializeCanvasItem($c))->values()->toArray() ?? [],
        ];
    }
}
