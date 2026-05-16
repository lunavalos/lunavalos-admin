<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteAddon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Genera una nueva Cotización (Borrador) clonando el contrato actual para iniciar
 * el flujo de renovación. No modifica el contrato original; al aceptarse la nueva
 * cotización y convertirla, el sistema enlazará renewed_contract_id en Fase 8.
 */
class StartContractRenewal
{
    public function __invoke(Contract $contract, ?User $actor = null): Quote
    {
        return DB::transaction(function () use ($contract, $actor) {
            $client = $contract->client;
            $oldQuote = $contract->quote;

            $quote = Quote::create([
                'package_service_id'           => $oldQuote?->package_service_id,
                'package_payment_plan_months'  => $contract->payment_plan_months,
                'client_id'                    => $client?->id,
                'client_name'                  => $client?->business_name  ?? $contract->legal_name,
                'contact_name'                 => $client?->contact_name   ?? $contract->legal_representative,
                'email'                        => $client?->email,
                'phone'                        => $client?->phone,
                'status'                       => 'Borrador',
                'issue_date'                   => now()->toDateString(),
                'valid_until'                  => now()->addDays((int) config('quotes.default_validity_days', 15))->toDateString(),
                'subtotal'                     => $contract->subtotal,
                'discount_amount'              => $contract->discount_amount,
                'tax_regime'                   => $client?->tax_regime,
                'applies_iva'                  => (float) $contract->iva_amount > 0,
                'iva_rate'                     => (float) config('sat.iva_rate', 16),
                'iva_amount'                   => $contract->iva_amount,
                'applies_isr_retention'        => false,
                'isr_retention_rate'           => 0,
                'isr_retention_amount'         => 0,
                'applies_iva_retention'        => false,
                'iva_retention_rate'           => 0,
                'iva_retention_amount'         => 0,
                'total'                        => $contract->total_amount,
                'is_multiple_choice'           => false,
                'legacy'                       => false,
                'notes'                        => "Renovación de contrato {$contract->contract_number}",
            ]);

            // Clonar addons si existen.
            foreach ($oldQuote?->addons ?? [] as $addon) {
                QuoteAddon::create([
                    'quote_id'             => $quote->id,
                    'service_addon_id'     => $addon->service_addon_id,
                    'quantity'             => $addon->quantity,
                    'unit_price'           => $addon->unit_price,
                    'billing_cycle'        => $addon->billing_cycle,
                    'billing_cycle_months' => $addon->billing_cycle_months,
                    'is_required'          => $addon->is_required,
                ]);
            }

            $contract->update(['renewal_status' => 'pending']);

            return $quote;
        });
    }
}
