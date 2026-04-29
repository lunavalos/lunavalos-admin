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
            $query->whereBetween('created_at', [
                $request->from . ' 00:00:00',
                $request->to   . ' 23:59:59',
            ]);
        }

        $tickets = $query->latest()
            ->get(['id', 'title', 'status', 'priority', 'created_at', 'client_service_id', 'assigned_id', 'deleted_at']);

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'title'        => 'required|string|max:255',
            'period_month' => 'required|integer|between:1,12',
            'period_year'  => 'required|integer|min:2020|max:2100',
            'summary'      => 'nullable|string',
            'notes'        => 'nullable|string',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        // Auto-resolve all tickets in the date range
        $ticketIds = Ticket::where('client_id', $request->client_id)
            ->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to   . ' 23:59:59',
            ])
            ->pluck('id')
            ->toArray();

        $report = Report::create([
            'client_id'    => $request->client_id,
            'created_by'   => Auth::id(),
            'title'        => $request->title,
            'period_month' => $request->period_month,
            'period_year'  => $request->period_year,
            'summary'      => $request->summary,
            'notes'        => $request->notes,
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
        $report->load(['client', 'creator']);
        $report->period_label = $report->period_label;

        // Use the self-contained snapshot so deleted tickets still appear
        $tickets = collect($report->tickets_snapshot ?? [])
            ->values()
            ->toArray();

        return Inertia::render('Reports/Show', [
            'report'  => $report,
            'tickets' => $tickets,
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
            'title'        => 'required|string|max:255',
            'period_month' => 'required|integer|between:1,12',
            'period_year'  => 'required|integer|min:2020|max:2100',
            'summary'      => 'nullable|string',
            'notes'        => 'nullable|string',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        $report->update([
            'title'        => $request->title,
            'period_month' => $request->period_month,
            'period_year'  => $request->period_year,
            'summary'      => $request->summary,
            'notes'        => $request->notes,
        ]);

        // Auto-resolve tickets in the new date range
        $ticketIds = Ticket::where('client_id', $report->client_id)
            ->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to   . ' 23:59:59',
            ])
            ->pluck('id')
            ->toArray();

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
        $report->load(['client', 'creator']);
        $report->period_label = $report->period_label;

        // Use snapshot so the PDF is consistent even if tickets were deleted
        $tickets = collect($report->tickets_snapshot ?? [])->values()->toArray();

        $settings = Setting::pluck('value', 'key')->toArray();

        $pdf = Pdf::loadView('reports.pdf', [
            'report'   => $report,
            'tickets'  => $tickets,
            'settings' => $settings,
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
            ->with(['clientService:id,service_name', 'assigned:id,name', 'creator:id,name'])
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
                ];
            })
            ->toArray();
    }
}
