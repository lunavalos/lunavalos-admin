<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettlePaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Contract;
use App\Support\Money\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Pagos',        only: ['index', 'showContract']),
            new Middleware('can:Registrar Pagos',  only: ['store', 'settle', 'cancel']),
        ];
    }

    /**
     * Vista de Cobranza agrupada por contrato.
     * Cada fila representa un contrato con su progreso de pagos.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['tab', 'q', 'client_id']);
        $tab = in_array($filters['tab'] ?? null, ['pendientes', 'pagados', 'vencidos'], true)
            ? $filters['tab']
            : 'pendientes';

        $contractsQuery = Contract::query()
            ->with(['client:id,business_name,contact_name,email,phone'])
            ->withCount([
                'payments',
                'payments as paid_payments_count' => fn ($q) => $q->whereIn('status', ['registrado', 'conciliado', 'facturado']),
                'payments as overdue_payments_count' => fn ($q) => $q->where('status', 'programado')->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->withSum([
                'payments as collected_amount' => fn ($q) => $q->whereIn('status', ['registrado', 'conciliado', 'facturado']),
            ], 'amount')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(function ($w) use ($v) {
                    $w->where('contract_number', 'like', "%{$v}%")
                      ->orWhereHas('client', fn ($c) => $c->where('business_name', 'like', "%{$v}%")->orWhere('contact_name', 'like', "%{$v}%"));
                });
            })
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->orderByDesc('id');

        // Aplica el filtro por tab usando HAVING sobre los agregados.
        $contracts = $contractsQuery->get()->map(function (Contract $c) {
            $total     = (float) ($c->total_amount ?? 0);
            $collected = (float) ($c->collected_amount ?? 0);
            $pending   = max(0, $total - $collected);
            $progress  = $total > 0 ? round(($collected / $total) * 100, 1) : 0;

            return [
                'id'                  => $c->id,
                'contract_number'     => $c->contract_number,
                'status'              => $c->status,
                'currency'            => $c->currency ?? config('currencies.default'),
                'exchange_rate'       => (float) ($c->exchange_rate ?? 1),
                'client'              => $c->client ? [
                    'id'            => $c->client->id,
                    'business_name' => $c->client->business_name,
                    'contact_name'  => $c->client->contact_name,
                ] : null,
                'total_amount'        => $total,
                'collected_amount'    => $collected,
                'pending_amount'      => $pending,
                'progress_pct'        => $progress,
                'payments_count'      => $c->payments_count,
                'paid_payments_count' => $c->paid_payments_count,
                'overdue_payments_count' => $c->overdue_payments_count,
                'is_fully_paid'       => $pending <= 0.009,
                'has_overdue'         => $c->overdue_payments_count > 0,
            ];
        });

        $contracts = match ($tab) {
            'pagados'  => $contracts->filter(fn ($c) => $c['is_fully_paid'])->values(),
            'vencidos' => $contracts->filter(fn ($c) => $c['has_overdue'])->values(),
            default    => $contracts->filter(fn ($c) => ! $c['is_fully_paid'])->values(),
        };

        // KPIs globales (de toda la cartera, no del tab).
        // Multi-moneda: agrupamos por moneda y además entregamos un total
        // normalizado a la moneda base usando el exchange_rate snapshot de
        // cada contrato. NUNCA sumes amount entre divisas sin convertir.
        $totals = Contract::query()
            ->withSum([
                'payments as collected_amount' => fn ($q) => $q->whereIn('status', ['registrado', 'conciliado', 'facturado']),
            ], 'amount')
            ->get(['id', 'total_amount', 'currency', 'exchange_rate']);

        $base = config('currencies.base', 'MXN');
        $byCurrency = [];
        foreach ($totals as $c) {
            $cur = $c->currency ?? $base;
            $byCurrency[$cur] ??= ['currency' => $cur, 'expected' => 0.0, 'collected' => 0.0, 'pending' => 0.0, 'count' => 0];
            $expected  = (float) ($c->total_amount ?? 0);
            $collected = (float) ($c->collected_amount ?? 0);
            $byCurrency[$cur]['expected']  += $expected;
            $byCurrency[$cur]['collected'] += $collected;
            $byCurrency[$cur]['pending']   += max(0, $expected - $collected);
            $byCurrency[$cur]['count']     += 1;
        }
        // Total normalizado a base
        $expectedBase = $collectedBase = 0.0;
        foreach ($totals as $c) {
            $rate = (float) ($c->exchange_rate ?: 1);
            $expectedBase  += (float) ($c->total_amount ?? 0) * $rate;
            $collectedBase += (float) ($c->collected_amount ?? 0) * $rate;
        }

        $kpis = [
            'contracts_count'           => $totals->count(),
            'base_currency'             => $base,
            'expected_total_base'       => round($expectedBase, 2),
            'collected_total_base'      => round($collectedBase, 2),
            'pending_total_base'        => round(max(0, $expectedBase - $collectedBase), 2),
            'by_currency'               => array_values($byCurrency),
            // Retro-compat con la UI actual (asume MXN). DEPRECATED.
            'expected_total'            => round($expectedBase, 2),
            'collected_total'           => round($collectedBase, 2),
        ];
        $kpis['pending_total'] = $kpis['pending_total_base'];

        return Inertia::render('Payments/Index', [
            'contracts' => $contracts,
            'filters'   => array_merge(['tab' => $tab], $filters),
            'kpis'      => $kpis,
        ]);
    }

    /**
     * Detalle de pagos + facturas de un contrato (vista de "cuenta del cliente").
     */
    public function showContract(Contract $contract)
    {
        $contract->load([
            'client:id,business_name,contact_name,email,phone,rfc,tax_regime',
            'quote:id,client_name,subtotal,total',
        ]);

        $payments = $contract->payments()
            ->with(['registeredBy:id,name'])
            ->orderByRaw('COALESCE(due_date, paid_at, created_at) ASC')
            ->get();

        $invoices = \App\Models\Invoice::query()
            ->where('contract_id', $contract->id)
            ->orderByDesc('id')
            ->get(['id', 'client_payment_id', 'uuid', 'folio', 'series', 'total', 'status', 'issued_at', 'xml_path', 'pdf_path']);

        $collected = (float) $payments->whereIn('status', ['registrado', 'conciliado', 'facturado', 'pagado'])->sum('amount');
        $total     = (float) $contract->total_amount;
        $pending   = max(0, $total - $collected);

        return Inertia::render('Payments/Contract', [
            'contract' => [
                'id'              => $contract->id,
                'contract_number' => $contract->contract_number,
                'status'          => $contract->status,
                'currency'        => $contract->currency ?? config('currencies.default'),
                'exchange_rate'   => (float) ($contract->exchange_rate ?? 1),
                'start_date'      => optional($contract->start_date)->toDateString(),
                'end_date'        => optional($contract->end_date)->toDateString(),
                'total_amount'    => $total,
                'monthly_amount'  => (float) $contract->monthly_amount,
                'anticipo_amount' => (float) $contract->anticipo_amount,
                'pdf_file_path'   => $contract->pdf_file_path,
                'client'          => $contract->client,
            ],
            'payments'  => $payments,
            'invoices'  => $invoices,
            'totals'    => [
                'total'     => $total,
                'collected' => $collected,
                'pending'   => $pending,
                'progress'  => $total > 0 ? round(($collected / $total) * 100, 1) : 0,
            ],
            'paymentMethods' => ClientPayment::METHODS,
            'types'          => ClientPayment::TYPES,
        ]);
    }

    public function store(StorePaymentRequest $request, CurrencyService $fx)
    {
        $data = $request->validated();

        // Inferir moneda del contrato si no se mandó; sino, validador ya forzó
        // que coincidan. Snapshot FX al día del pago para reportes consolidados.
        $contract = $data['contract_id'] ? Contract::find($data['contract_id']) : null;
        $currency = strtoupper($data['currency']
            ?? ($contract?->currency)
            ?? config('currencies.default'));
        $data['currency']      = $currency;
        $data['exchange_rate'] = $contract && $contract->currency === $currency
            ? ($contract->exchange_rate ?: 1)
            : $fx->snapshotRate($currency, $data['paid_at'] ?? null);

        $data['registered_by_user_id'] = $request->user()->id;
        $data['status']  = 'registrado';

        if ($request->hasFile('evidence_file')) {
            $data['evidence_file_path'] = $request->file('evidence_file')
                ->store("payments/{$data['client_id']}", 'public');
        }
        unset($data['evidence_file']);

        ClientPayment::create($data);

        return back()->with('success', 'Pago registrado correctamente.');
    }

    /**
     * Marca como pagada una mensualidad programada (status='programado') o
     * una mensualidad facturada por adelantado (status='facturado'). En el
     * segundo caso el CFDI ya existe y sólo registramos el cobro/conciliación.
     */
    public function settle(SettlePaymentRequest $request, ClientPayment $payment)
    {
        if (! in_array($payment->status, ['programado', 'facturado'], true)) {
            return back()->withErrors(['payment' => 'Solo se pueden cobrar pagos programados o facturados por adelantado.']);
        }

        // Si ya estaba facturado, lo dejamos como conciliado (CFDI + pago);
        // si era programado, queda como registrado para timbrar después.
        $nextStatus = $payment->status === 'facturado' ? 'conciliado' : 'registrado';

        $payload = [
            'status'                => $nextStatus,
            'paid_at'               => $request->paid_at,
            'payment_method'        => $request->payment_method,
            'reference'             => $request->reference,
            'notes'                 => $request->notes,
            'registered_by_user_id' => $request->user()->id,
        ];

        if ($request->filled('amount')) {
            $payload['amount'] = $request->amount;
        }

        if ($request->hasFile('evidence_file')) {
            $payload['evidence_file_path'] = $request->file('evidence_file')
                ->store("payments/{$payment->client_id}", 'public');
        }

        $payment->update($payload);

        return back()->with('success', "Pago «{$payment->concept}» registrado.");
    }

    public function cancel(Request $request, ClientPayment $payment)
    {
        if (! in_array($payment->status, ['programado', 'registrado'], true)) {
            return back()->withErrors(['payment' => 'No se puede cancelar un pago en estado: ' . $payment->status]);
        }

        $payment->update([
            'status' => 'cancelado',
            'notes'  => trim(($payment->notes ?? '') . "\nCancelado por " . $request->user()->name . ' el ' . now()->toDateTimeString()),
        ]);

        return back()->with('success', 'Pago cancelado.');
    }

    /**
     * Genera el PDF de solicitud de pago / recibo para un ClientPayment.
     * Funciona en cualquier moneda (incluye USD) y para pagos programados
     * (aún no cobrados) — sirve como "factura proforma" o solicitud de cobro.
     */
    public function receipt(ClientPayment $payment)
    {
        $payment->loadMissing('client', 'contract');

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'DomPDF no está instalado.');
        }

        $client = $payment->client;
        if (! $client) {
            abort(404, 'Pago sin cliente asociado.');
        }

        $client->next_renewal_date = $payment->due_date ?? $payment->paid_at ?? now()->toDateString();

        $receiptData = [
            'client'              => $client,
            'service_name'        => $payment->concept ?: ('Pago contrato ' . ($payment->contract?->contract_number ?? '')),
            'amount'              => (float) $payment->amount,
            'billing_type'        => $payment->type,
            'service_description' => $payment->reference ? 'Referencia: ' . $payment->reference : null,
            'currency'            => strtoupper($payment->currency ?: ($client->currency ?? config('currencies.default', 'MXN'))),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData)
            ->setPaper('letter', 'portrait');

        $filename = 'solicitud-pago-' . str_replace(' ', '-', strtolower($client->business_name ?? 'cliente'))
                  . '-' . $payment->id . '.pdf';

        return $pdf->stream($filename);
    }
}
