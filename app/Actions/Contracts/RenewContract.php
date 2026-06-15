<?php

namespace App\Actions\Contracts;

use App\Models\ClientPayment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RenewContract
{
    public function __invoke(Contract $contract, ?User $actor = null): Contract
    {
        return DB::transaction(function () use ($contract, $actor) {
            $months = $contract->payment_plan_months ?: 12;

            // Si ya venció, renovar desde hoy; si aún está vigente, extender desde end_date
            $base = ($contract->end_date && $contract->end_date->isFuture())
                ? $contract->end_date->copy()
                : now()->startOfDay();

            $newEndDate = $base->addMonths($months);

            $contract->update([
                'end_date'       => $newEndDate,
                'renewal_status' => 'renewed',
            ]);

            ClientPayment::create([
                'client_id'             => $contract->client_id,
                'contract_id'           => $contract->id,
                'registered_by_user_id' => $actor?->id,
                'type'                  => 'pago_unico',
                'status'                => 'programado',
                'concept'               => "Renovación — Contrato {$contract->contract_number}",
                'amount'                => $contract->total_amount,
                'currency'              => $contract->currency ?? config('currencies.default'),
                'exchange_rate'         => $contract->exchange_rate ?? 1,
                'due_date'              => now()->toDateString(),
            ]);

            return $contract->fresh();
        });
    }
}
