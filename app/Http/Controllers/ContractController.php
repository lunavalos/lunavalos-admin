<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ContractController extends Controller
{
    /**
     * Admin: list all contracts with KPIs.
     */
    public function index(Request $request)
    {
        $query = Contract::with(['client:id,name,email', 'quote:id,client_id', 'payments:id,contract_id,amount,status'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($renewal = $request->input('renewal_status')) {
            $query->where('renewal_status', $renewal);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        $contracts = $query->get();

        // KPIs
        $signed   = $contracts->where('status', 'signed');
        $pending  = $contracts->where('status', 'pending');
        $totalSigned    = $signed->sum(fn ($c) => (float) $c->total_amount);
        $totalPending   = $pending->sum(fn ($c) => (float) $c->total_amount);
        $mrr = $signed
            ->filter(fn ($c) => ! in_array($c->renewal_status, ['renewed', 'declined']))
            ->sum(fn ($c) => (float) $c->monthly_amount);
        $upcoming90 = $signed->filter(function ($c) {
            if (! $c->end_date) return false;
            $days = now()->startOfDay()->diffInDays($c->end_date->startOfDay(), false);
            return $days >= 0 && $days <= 90 && ! in_array($c->renewal_status, ['renewed', 'declined']);
        })->count();
        $overdue = $signed->filter(function ($c) {
            if (! $c->end_date) return false;
            return $c->end_date->isPast() && ! in_array($c->renewal_status, ['renewed', 'declined']);
        })->count();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters'   => $request->only(['status', 'renewal_status', 'search', 'client_id']),
            'kpis'      => [
                'total_signed_amount'  => $totalSigned,
                'total_pending_amount' => $totalPending,
                'mrr'                  => $mrr,
                'upcoming_90d'         => $upcoming90,
                'overdue'              => $overdue,
                'count_total'          => $contracts->count(),
                'count_signed'         => $signed->count(),
                'count_pending'        => $pending->count(),
            ],
        ]);
    }

    /**
     * Generate a new contract for the given quote.
     */
    public function generate(Request $request, Quote $quote)
    {
        // Don't generate if one already exists
        if ($quote->contract) {
            return back()->with('error', 'El contrato ya fue generado.');
        }

        $contract = $quote->contract()->create([
            'token' => Str::random(32),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Enlace de contrato generado correctamente.');
    }

    /**
     * Show the public contract view to the client.
     */
    public function show($token)
    {
        $contract = Contract::where('token', $token)->with('quote.items')->firstOrFail();
        $settings = Setting::pluck('value', 'key')->toArray();

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'settings' => $settings,
        ]);
    }

    /**
     * Process contract signing from the client.
     */
    public function sign(Request $request, $token)
    {
        $contract = Contract::where('token', $token)->firstOrFail();

        if ($contract->status === 'signed') {
            return back()->with('error', 'El contrato ya ha sido firmado.');
        }

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:255',
            'fiscal_address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'legal_representative' => 'nullable|string|max:255',
            'csf_file' => 'required|file|mimes:pdf,jpg,png|max:10240',
            'accept_terms' => 'required|accepted',
        ]);

        // store csf
        if ($request->hasFile('csf_file')) {
            $contract->csf_file_path = $request->file('csf_file')->store('contracts', 'public');
        }

        $contract->legal_name = $validated['legal_name'];
        $contract->tax_id = $validated['tax_id'];
        $contract->fiscal_address = $validated['fiscal_address'];
        $contract->postal_code = $validated['postal_code'];
        $contract->legal_representative = $validated['legal_representative'];
        $contract->signed_at = now();
        $contract->signature_ip = $request->ip();
        $contract->status = 'signed';
        $contract->save();

        // Update quote status
        $contract->quote->update([
            'status' => 'Contrato Firmado'
        ]);

        // In a real scenario, you'd generate the final PDF here and shoot an email.
        // For now, we just redirect back with a success message.
        return back()->with('success', 'Contrato firmado legalmente.');
    }

    /**
     * Download signed contract as PDF (public, token-gated).
     */
    public function downloadPdf($token)
    {
        $contract = Contract::where('token', $token)->with('quote.items')->firstOrFail();

        if ($contract->status !== 'signed') {
            abort(403, 'El contrato aún no ha sido firmado.');
        }

        $settings = Setting::pluck('value', 'key')->toArray();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('contracts.pdf', [
            'contract' => $contract,
            'settings' => $settings,
        ])->setPaper('letter', 'portrait');

        $filename = 'contrato-' . ($contract->contract_number ?? $contract->id) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Admin: detail view for a single contract.
     */
    public function adminShow(Contract $contract)    {
        $contract->load([
            'client:id,name,email',
            'quote:id,client_name,contact_name,email,phone,status,total,issue_date',
            'payments' => fn ($q) => $q->orderBy('due_date')->orderBy('created_at'),
            'createdBy:id,name',
            'renewedContract:id,contract_number',
            'contractServices',
            'activeBillingCycle.credits.contractService',
        ]);

        $totalPaid     = $contract->payments->whereIn('status', ['registrado', 'conciliado', 'facturado'])->sum(fn ($p) => (float) $p->amount);
        $totalPending  = $contract->payments->whereIn('status', ['programado'])->sum(fn ($p) => (float) $p->amount);
        $daysUntilEnd  = $contract->daysUntilEnd();

        $activeCycle = $contract->activeBillingCycle;

        return Inertia::render('Contracts/AdminShow', [
            'contract'         => $contract,
            'totalPaid'        => $totalPaid,
            'totalPending'     => $totalPending,
            'daysUntilEnd'     => $daysUntilEnd,
            'contractServices' => $contract->contractServices,
            'activeCycle'      => $activeCycle ? [
                'id'           => $activeCycle->id,
                'period_start' => $activeCycle->period_start->toDateString(),
                'period_end'   => $activeCycle->period_end->toDateString(),
                'label'        => $activeCycle->period_start->format('M Y'),
                'status'       => $activeCycle->status,
                'credits'      => $activeCycle->credits->map(fn ($cr) => [
                    'id'                  => $cr->id,
                    'contract_service_id' => $cr->contract_service_id,
                    'total'               => (int) $cr->total,
                    'rolled_over'         => (int) $cr->rolled_over,
                    'consumed'            => (int) $cr->consumed,
                    'is_unlimited'        => (bool) $cr->is_unlimited,
                ]),
            ] : null,
        ]);
    }
}
