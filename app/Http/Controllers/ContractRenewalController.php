<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\StartContractRenewal;
use App\Console\Commands\CheckContractRenewals;
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
            new Middleware('can:Ver Contratos',  only: ['index']),
            new Middleware('can:Editar Contratos', only: ['start', 'decline', 'runCheck']),
        ];
    }

    public function index(Request $request)
    {
        $notifyMax = max(config('quotes.renewals.notify_days_before', [60]));
        $today     = now()->startOfDay();

        $contracts = Contract::query()
            ->with(['client:id,business_name,email', 'quote:id,client_name'])
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

    public function start(Request $request, Contract $contract, StartContractRenewal $action)
    {
        $quote = $action($contract, $request->user());

        return redirect()->route('quotes.manage', $quote)
            ->with('success', "Cotización de renovación creada para {$contract->contract_number}.");
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
