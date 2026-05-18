<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\ClientCost;
use App\Models\ClientService;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * Materializa en el Cliente la información derivada de su Cotización:
 *  - Datos fiscales (sólo rellena si el cliente no los tiene).
 *  - Servicios y Renovaciones (ClientService) — clave: client_id + service_id (o concept).
 *  - Costos Internos (ClientCost) — clave: client_id + concept.
 *
 * 100% idempotente: usa updateOrCreate. Re-ejecutar no duplica nada
 * y nunca sobrescribe campos fiscales ya capturados manualmente.
 *
 * Es la ÚNICA fuente de verdad para poblar estas secciones desde un Quote,
 * invocada tanto por ConvertQuoteToContract (automático) como por el comando
 * de backfill `clients:sync-from-contracts`.
 */
class SyncClientFromQuote
{
    public function __invoke(Client $client, Quote $quote): array
    {
        return DB::transaction(function () use ($client, $quote) {
            $quote->loadMissing(['items.costs', 'package', 'addons.serviceAddon.costs']);

            $fiscal   = $this->syncFiscal($client, $quote);
            $services = $this->syncServices($client, $quote)
                      + $this->syncAddonsAsServices($client, $quote);
            $costs    = $this->syncCosts($client, $quote)
                      + $this->syncAddonCosts($client, $quote);

            return compact('fiscal', 'services', 'costs');
        });
    }

    /**
     * Rellena campos fiscales del cliente sólo si vienen vacíos.
     * No sobrescribe datos que el admin haya capturado a mano.
     */
    private function syncFiscal(Client $client, Quote $quote): int
    {
        $candidates = [
            'tax_regime'            => $quote->tax_regime,
            'applies_iva'           => $quote->applies_iva,
            'iva_rate'              => $quote->iva_rate,
            'applies_isr_retention' => $quote->applies_isr_retention,
            'isr_retention_rate'    => $quote->isr_retention_rate,
            'applies_iva_retention' => $quote->applies_iva_retention,
            'iva_retention_rate'    => $quote->iva_retention_rate,
        ];

        $fill = [];
        foreach ($candidates as $col => $val) {
            if ($val === null) {
                continue;
            }
            $current = $client->getAttribute($col);
            // Para booleans no podemos usar blank(): false es válido. Sólo rellenamos si es null.
            if (is_bool($val)) {
                if ($current === null) {
                    $fill[$col] = $val;
                }
            } elseif (blank($current)) {
                $fill[$col] = $val;
            }
        }

        if (!empty($fill)) {
            $client->fill($fill)->save();
        }

        return count($fill);
    }

    /**
     * Materializa Servicios y Renovaciones desde el paquete del Quote y sus items.
     */
    private function syncServices(Client $client, Quote $quote): int
    {
        $count = 0;

        // 1. Servicio principal (paquete) de la cotización.
        if ($quote->package_service_id && $quote->package) {
            $service = $quote->package;
            $billing = $this->mapServiceBilling($service->billing_type ?? 'annual');
            $amount  = $billing === 'monthly'
                ? (float) ($service->price ?? 0)
                : (float) ($service->renewal_price ?? $service->price ?? 0);

            ClientService::updateOrCreate(
                [
                    'client_id'  => $client->id,
                    'service_id' => $service->id,
                ],
                [
                    'service_name'    => $service->name,
                    'renewal_amount'  => $amount,
                    'billing_type'    => $billing,
                    'initial_payment' => (float) ($service->price ?? 0),
                    'initial_cost'    => 0,
                    'status'          => 'active',
                ]
            );
            $count++;
        }

        // 2. Items individuales de la cotización (si los hay).
        foreach ($quote->items as $item) {
            $billing      = $this->mapServiceBilling($item->billing_type);
            $unitPrice    = (float) ($item->unit_price ?? 0);
            $renewalPrice = (float) ($item->unit_renewal_price ?? $unitPrice);
            $qty          = (float) ($item->quantity ?? 1);

            $lookup = $item->service_id
                ? ['client_id' => $client->id, 'service_id' => $item->service_id]
                : ['client_id' => $client->id, 'service_id' => null, 'service_name' => $item->concept];

            ClientService::updateOrCreate(
                $lookup,
                [
                    'service_name'    => $item->concept,
                    'renewal_amount'  => $renewalPrice,
                    'billing_type'    => $billing,
                    'initial_payment' => $unitPrice * $qty,
                    'initial_cost'    => 0,
                    'status'          => 'active',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Materializa Costos Internos desde QuoteItemCost.
     */
    private function syncCosts(Client $client, Quote $quote): int
    {
        $count = 0;

        foreach ($quote->items as $item) {
            $frequency = $this->mapCostFrequency($item->billing_type);

            foreach ($item->costs as $cost) {
                $concept = trim($cost->title . ' (' . $item->concept . ')');
                $amount  = (float) $cost->price * (float) ($cost->quantity ?? 1);

                ClientCost::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'concept'   => $concept,
                    ],
                    [
                        'amount'            => $amount,
                        'billing_frequency' => $frequency,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vocabulario de ClientService.billing_type: monthly | annual | once
     */
    private function mapServiceBilling(?string $type): string
    {
        return match ($type) {
            'monthly'                                         => 'monthly',
            'unique', 'once'                                  => 'once',
            default                                           => 'annual',
        };
    }

    /**
     * Vocabulario de ClientCost.billing_frequency: monthly | annual | unique
     */
    private function mapCostFrequency(?string $type): string
    {
        return match ($type) {
            'monthly'                                         => 'monthly',
            'unique', 'once'                                  => 'unique',
            default                                           => 'annual',
        };
    }

    /**
     * Materializa los addons de la cotización como ClientService.
     * Los addons viven en `service_addons` (no en `services`), por lo que
     * usamos service_id=null + service_name con prefijo "[Addon]" como
     * llave estable para updateOrCreate.
     */
    private function syncAddonsAsServices(Client $client, Quote $quote): int
    {
        $count = 0;

        foreach ($quote->addons as $addon) {
            $sa = $addon->serviceAddon;
            if (!$sa) {
                continue;
            }

            $name    = '[Addon] ' . $sa->name;
            $qty     = (float) ($addon->quantity ?? 1);
            $unit    = (float) ($addon->unit_price ?? $sa->price ?? 0);
            $billing = $this->mapAddonBilling($addon->billing_cycle ?? $sa->billing_cycle);

            ClientService::updateOrCreate(
                [
                    'client_id'    => $client->id,
                    'service_id'   => null,
                    'service_name' => $name,
                ],
                [
                    'renewal_amount'  => $unit * $qty,
                    'billing_type'    => $billing,
                    'initial_payment' => $billing === 'once' ? $unit * $qty : 0,
                    'initial_cost'    => 0,
                    'status'          => 'active',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Materializa los costos internos de cada addon (ServiceAddonCost)
     * como ClientCost, para poder medir utilidad real cuando la cotización
     * incluye addons recurrentes (hosting, dominios, software de terceros).
     */
    private function syncAddonCosts(Client $client, Quote $quote): int
    {
        $count = 0;

        foreach ($quote->addons as $addon) {
            $sa = $addon->serviceAddon;
            if (!$sa) {
                continue;
            }

            $frequency = $this->mapAddonCostFrequency($addon->billing_cycle ?? $sa->billing_cycle);
            $qty       = (float) ($addon->quantity ?? 1);

            foreach ($sa->costs as $cost) {
                $concept = trim($cost->title . ' (Addon: ' . $sa->name . ')');
                $amount  = (float) $cost->price * (float) ($cost->quantity ?? 1) * $qty;

                ClientCost::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'concept'   => $concept,
                    ],
                    [
                        'amount'            => $amount,
                        'billing_frequency' => $frequency,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * ServiceAddon.billing_cycle: once|monthly|quarterly|semiannual|annual|custom_months
     * -> ClientService.billing_type: monthly | annual | once
     */
    private function mapAddonBilling(?string $cycle): string
    {
        return match ($cycle) {
            'once'    => 'once',
            'monthly' => 'monthly',
            default   => 'annual', // quarterly, semiannual, annual, custom_months
        };
    }

    /**
     * ServiceAddon.billing_cycle -> ClientCost.billing_frequency: monthly | annual | unique
     */
    private function mapAddonCostFrequency(?string $cycle): string
    {
        return match ($cycle) {
            'once'    => 'unique',
            'monthly' => 'monthly',
            default   => 'annual',
        };
    }
}
