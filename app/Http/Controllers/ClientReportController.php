<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Setting;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientReportController extends Controller
{
    // -------------------------------------------------------------------------
    // Guard: ensure the authenticated user has a linked client
    // -------------------------------------------------------------------------
    private function clientId(): int
    {
        $id = Auth::user()->client_id;
        if (!$id) abort(403, 'Tu cuenta no está vinculada a ningún cliente.');
        return $id;
    }

    // -------------------------------------------------------------------------
    // List all reports for this client
    // -------------------------------------------------------------------------
    public function index()
    {
        $clientId = $this->clientId();

        $reports = Report::where('client_id', $clientId)
            ->with(['creator'])
            ->withCount('tickets')
            ->latest()
            ->get()
            ->map(function ($r) {
                $r->period_label = $r->period_label;
                return $r;
            });

        return Inertia::render('ClientPanel/Reports/Index', [
            'reports' => $reports,
        ]);
    }

    // -------------------------------------------------------------------------
    // Create form
    // -------------------------------------------------------------------------
    public function create()
    {
        $clientId = $this->clientId();
        $client   = \App\Models\Client::find($clientId, ['id', 'business_name']);

        return Inertia::render('ClientPanel/Reports/Create', [
            'client' => $client,
        ]);
    }

    // -------------------------------------------------------------------------
    // Preview tickets for the client's date range (AJAX)
    // -------------------------------------------------------------------------
    public function myTickets(Request $request)
    {
        $clientId = $this->clientId();

        $query = Ticket::where('client_id', $clientId)
            ->with(['clientService', 'assigned']);

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [
                $request->from . ' 00:00:00',
                $request->to   . ' 23:59:59',
            ]);
        }

        $tickets = $query->latest()
            ->get(['id', 'title', 'status', 'priority', 'created_at', 'client_service_id', 'assigned_id']);

        return response()->json($tickets);
    }

    // -------------------------------------------------------------------------
    // Store a new report
    // -------------------------------------------------------------------------
    public function store(Request $request)
    {
        $clientId = $this->clientId();

        $request->validate([
            'title'        => 'required|string|max:255',
            'period_month' => 'required|integer|between:1,12',
            'period_year'  => 'required|integer|min:2020|max:2100',
            'summary'      => 'nullable|string',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        // Auto-resolve all tickets in the date range for this client
        $ticketIds = Ticket::where('client_id', $clientId)
            ->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to   . ' 23:59:59',
            ])
            ->pluck('id')
            ->toArray();

        $report = Report::create([
            'client_id'    => $clientId,
            'created_by'   => Auth::id(),
            'title'        => $request->title,
            'period_month' => $request->period_month,
            'period_year'  => $request->period_year,
            'summary'      => $request->summary,
            'notes'        => null,
        ]);

        if (!empty($ticketIds)) {
            $report->tickets()->sync($ticketIds);
        }

        $report->tickets_snapshot = $this->buildTicketsSnapshot($ticketIds);
        $report->save();

        return redirect()->route('client.reports.show', $report)
            ->with('success', 'Reporte generado correctamente.');
    }

    // -------------------------------------------------------------------------
    // Show a single report (read-only for client)
    // -------------------------------------------------------------------------
    public function show(Report $report)
    {
        $clientId = $this->clientId();
        if ($report->client_id !== $clientId) abort(403);

        $report->load(['client', 'creator']);
        $report->period_label = $report->period_label;

        $tickets = collect($report->tickets_snapshot ?? [])->values()->toArray();

        return Inertia::render('ClientPanel/Reports/Show', [
            'report'  => $report,
            'tickets' => $tickets,
        ]);
    }

    // -------------------------------------------------------------------------
    // Download PDF
    // -------------------------------------------------------------------------
    public function pdf(Report $report)
    {
        $clientId = $this->clientId();
        if ($report->client_id !== $clientId) abort(403);

        $report->load(['client', 'creator']);
        $report->period_label = $report->period_label;

        $tickets  = collect($report->tickets_snapshot ?? [])->values()->toArray();
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
    // Private: snapshot builder (mirrors ReportController)
    // -------------------------------------------------------------------------
    private function buildTicketsSnapshot(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

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

                $startedAt  = $t->work_started_at ?? $t->created_at;
                $finishedAt = $t->work_finished_at;
                if (!$finishedAt) {
                    $entry      = $log->first(fn($m) => str_contains($m->message, 'Completados'));
                    $finishedAt = $entry?->created_at ?? null;
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
                    'assigned'         => $t->assigned     ? ['id' => $t->assigned->id,     'name' => $t->assigned->name]     : null,
                    'creator'          => $t->creator      ? ['id' => $t->creator->id,      'name' => $t->creator->name]      : null,
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
