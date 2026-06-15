<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\RenewContract;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class ContractRenewalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Contratos',   only: ['index', 'receipt']),
            new Middleware('can:Editar Contratos', only: ['start', 'decline', 'runCheck', 'sendReceipt']),
        ];
    }

    public function index(Request $request)
    {
        $notifyMax = max(config('quotes.renewals.notify_days_before', [60]));
        $today     = now()->startOfDay();

        $contracts = Contract::query()
            ->with(['client:id,business_name,email,domain_names', 'quote:id,client_name'])
            ->whereNotNull('end_date')
            ->whereNotIn('renewal_status', ['renewed', 'declined'])
            ->where(function ($q) use ($notifyMax) {
                $q->whereDate('end_date', '<=', now()->addDays($notifyMax)->toDateString())
                  ->orWhere('renewal_status', 'overdue');
            })
            ->orderBy('end_date')
            ->get()
            ->map(function (Contract $c) use ($today) {
                $diff = $c->end_date ? (int) $today->diffInDays($c->end_date, false) : null;
                return [
                    'id'                       => $c->id,
                    'contract_number'          => $c->contract_number,
                    'client_name'              => $c->client?->business_name ?? $c->quote?->client_name,
                    'client_email'             => $c->client?->email,
                    'end_date'                 => $c->end_date?->toDateString(),
                    'days_remaining'           => $diff,
                    'is_overdue'               => $diff !== null && $diff < 0,
                    'monthly_amount'           => (float) $c->monthly_amount,
                    'total_amount'             => (float) $c->total_amount,
                    'renewal_status'           => $c->renewal_status,
                    'renewal_last_notified_at' => $c->renewal_last_notified_at?->toIso8601String(),
                    'token'                    => $c->token,
                    'client_id'                => $c->client_id,
                    'currency'                 => $c->currency ?? config('currencies.default'),
                    'domain_names'             => $c->domain_names ?: $c->client?->domain_names,
                ];
            });

        // Forecast a 18 meses: oportunidad de renovación = total_amount de contratos
        // cuya end_date cae en ese mes y aún no estén renovados/declinados.
        $forecast = $this->buildRenewalForecast($today, 18);

        return Inertia::render('Contracts/Renewals', [
            'contracts' => $contracts,
            'forecast'  => $forecast,
            'kpis' => [
                'total'    => $contracts->count(),
                'overdue'  => $contracts->where('is_overdue', true)->count(),
                'next_30'  => $contracts->where('days_remaining', '>=', 0)->where('days_remaining', '<=', 30)->count(),
                'next_90'  => $contracts->where('days_remaining', '>=', 0)->where('days_remaining', '<=', 90)->count(),
                'notified' => $contracts->where('renewal_status', 'notified')->count(),
                'forecast_total_18m' => collect($forecast)->sum('amount'),
            ],
        ]);
    }

    private function buildRenewalForecast(\Carbon\Carbon $from, int $months): array
    {
        $start = $from->copy()->startOfMonth();
        $end   = $start->copy()->addMonths($months);

        $rows = Contract::query()
            ->whereNotNull('end_date')
            ->whereNotIn('renewal_status', ['renewed', 'declined'])
            ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'end_date', 'total_amount', 'monthly_amount', 'renewal_status']);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $buckets[$m->format('Y-m')] = [
                'month'  => $m->format('Y-m'),
                'label'  => $m->locale('es_MX')->isoFormat('MMM YY'),
                'amount' => 0.0,
                'count'  => 0,
            ];
        }
        foreach ($rows as $c) {
            $key = $c->end_date->format('Y-m');
            if (! isset($buckets[$key])) continue;
            $buckets[$key]['amount'] += (float) $c->total_amount;
            $buckets[$key]['count']  += 1;
        }
        return array_values($buckets);
    }

    public function start(Request $request, Contract $contract, RenewContract $action)
    {
        $action($contract, $request->user());

        return back()->with('success', "Contrato {$contract->contract_number} renovado. Pago pendiente registrado.");
    }

    private function buildReceiptData(Contract $contract): array
    {
        $client  = $contract->client;
        $months  = $contract->payment_plan_months ?: 12;
        $billing = $months === 1 ? 'monthly' : 'unique';
        $domain  = $contract->domain_names ?: $client?->domain_names;

        $serviceName = $domain
            ? "Renovación Anual — Servicios para: {$domain}"
            : "Renovación — Contrato {$contract->contract_number}";

        $client->next_renewal_date = $contract->end_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [
            'client'              => $client,
            'service_name'        => $serviceName,
            'service_description' => $domain ? "Contrato {$contract->contract_number}" : null,
            'amount'              => (float) $contract->total_amount,
            'billing_type'        => $billing,
            'currency'            => $contract->currency ?? config('currencies.default'),
        ];
    }

    public function receipt(Contract $contract)
    {
        $contract->load('client');

        if (! $contract->client) {
            return back()->with('error', 'Este contrato no tiene cliente asociado.');
        }

        $receiptData = $this->buildReceiptData($contract);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData);
        return $pdf->stream("Recibo_Renovacion_{$contract->contract_number}.pdf");
    }

    public function sendReceipt(Request $request, Contract $contract)
    {
        $contract->load('client');
        $client = $contract->client;

        if (! $client?->email) {
            return back()->with('error', 'Este contrato no tiene correo de cliente configurado.');
        }

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'No se pudo generar el PDF del recibo.');
        }

        $receiptData = $this->buildReceiptData($contract);
        $pdfContent  = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData)->output();

        try {
            \Illuminate\Support\Facades\Mail::to($client->email)
                ->cc('ventas@lunavalos.com')
                ->send(new \App\Mail\RenewalReceiptMail(
                    $client,
                    $receiptData['service_name'],
                    $receiptData['amount'],
                    $receiptData['billing_type'],
                    $pdfContent,
                    $receiptData['currency']
                ));

            $contract->update([
                'renewal_status'           => 'notified',
                'renewal_last_notified_at' => now(),
            ]);

            return back()->with('message', "¡Correo enviado a {$client->email} con éxito!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error al enviar el correo: ' . $e->getMessage());
        }
    }

    public function decline(Request $request, Contract $contract)
    {
        $contract->update(['renewal_status' => 'declined']);
        return back()->with('success', "Renovación declinada para {$contract->contract_number}.");
    }

    public function runCheck()
    {
        Artisan::call('contracts:check-renewals');
        return back()->with('success', 'Revisión de renovaciones ejecutada: ' . trim(Artisan::output()));
    }
}
