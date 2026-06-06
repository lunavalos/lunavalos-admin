<?php

namespace App\Actions\Recurring;

use App\Models\BillingCycle;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\DeliverableCredit;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Abre un nuevo ciclo de facturación para un contrato:
 *   1. Cierra el ciclo activo previo (status=closed).
 *   2. Calcula rollover (si aplica) por cada ContractService.
 *   3. Crea el nuevo BillingCycle (idempotente: unique contract_id + period_start).
 *   4. Genera DeliverableCredit por cada ContractService.
 *   5. Pre-crea tickets en el backlog para los servicios de tipo `fixed`
 *      con auto_create_tickets = true.
 *
 * Uso:
 *   app(OpenBillingCycle::class)($contract);                // ciclo del mes actual
 *   app(OpenBillingCycle::class)($contract, $periodStart);  // forzar fecha
 */
class OpenBillingCycle
{
    public function __invoke(Contract $contract, ?CarbonImmutable $periodStart = null): BillingCycle
    {
        $periodStart = ($periodStart ?? CarbonImmutable::now())->startOfDay()->startOfMonth();
        $periodEnd   = $periodStart->endOfMonth()->startOfDay();

        return DB::transaction(function () use ($contract, $periodStart, $periodEnd) {
            // 1. Cerrar ciclos previos activos del mismo contrato.
            $previous = BillingCycle::where('contract_id', $contract->id)
                ->where('status', 'active')
                ->where('period_start', '<', $periodStart)
                ->get();

            foreach ($previous as $prev) {
                $prev->update([
                    'status'    => 'closed',
                    'closed_at' => now(),
                ]);
            }

            // 2. Idempotencia: si ya existe el ciclo, devolverlo tal cual.
            $cycle = BillingCycle::firstOrCreate(
                [
                    'contract_id'  => $contract->id,
                    'period_start' => $periodStart->toDateString(),
                ],
                [
                    'period_end' => $periodEnd->toDateString(),
                    'status'     => 'active',
                ]
            );

            if (!$cycle->wasRecentlyCreated) {
                return $cycle;
            }

            // 3. Generar créditos + tickets por cada ContractService.
            $services = $contract->contractServices()->orderBy('sort_order')->get();

            foreach ($services as $service) {
                $rolledOver = $this->calculateRollover($service, $previous);

                $credit = DeliverableCredit::create([
                    'billing_cycle_id'      => $cycle->id,
                    'contract_service_id'   => $service->id,
                    'total'                 => $service->quantity_per_cycle,
                    'rolled_over'           => $rolledOver,
                    'consumed'              => 0,
                    'is_unlimited'          => $service->isUnlimited(),
                ]);

                if ($service->isFixed() && $service->auto_create_tickets) {
                    $this->preCreateTickets($contract, $cycle, $service, $credit);
                }
            }

            return $cycle->refresh();
        });
    }

    /**
     * Rollover: créditos no consumidos del ciclo anterior, si el servicio lo permite.
     */
    protected function calculateRollover(ContractService $service, $previousCycles): int
    {
        if (!$service->rollover_allowed || $previousCycles->isEmpty()) {
            return 0;
        }

        // Tomamos el ciclo cerrado más reciente.
        $prev = $previousCycles->sortByDesc('period_start')->first();

        $prevCredit = DeliverableCredit::where('billing_cycle_id', $prev->id)
            ->where('contract_service_id', $service->id)
            ->first();

        if (!$prevCredit || $prevCredit->is_unlimited) {
            return 0;
        }

        $capacity = (int) $prevCredit->total + (int) $prevCredit->rolled_over;
        return max(0, $capacity - (int) $prevCredit->consumed);
    }

    /**
     * Genera N tickets vacíos en estado "Nuevos" para los entregables fijos.
     */
    protected function preCreateTickets(
        Contract $contract,
        BillingCycle $cycle,
        ContractService $service,
        DeliverableCredit $credit
    ): void {
        $quantity = (int) $service->quantity_per_cycle;
        if ($quantity <= 0) {
            return;
        }

        $clientServiceId = $service->client_service_id;

        for ($i = 1; $i <= $quantity; $i++) {
            Ticket::create([
                'title'                 => sprintf('%s #%d — %s', $service->name, $i, $cycle->period_start->format('M Y')),
                'priority'              => 'Media',
                'status'                => 'Nuevos',
                'source_type'           => Ticket::SOURCE_RECURRING,
                'creator_id'            => $contract->created_by_user_id,
                'client_id'             => $contract->client_id,
                'billing_cycle_id'      => $cycle->id,
                'deliverable_credit_id' => $credit->id,
                'sequence_number'       => $i,
                'client_service_id'     => $clientServiceId,
            ]);
        }
    }
}
