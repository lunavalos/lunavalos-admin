<?php

namespace App\Http\Controllers;

use App\Actions\Recurring\OpenBillingCycle;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\DeliverableCredit;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecurringClientController extends Controller
{
    /**
     * Vista 360° del cliente recurrente: resumen del ciclo activo + Kanban filtrado.
     */
    public function show(Client $client)
    {
        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->with([
                'contractServices',
                'activeBillingCycle.credits.contractService',
            ])
            ->latest('id')
            ->firstOrFail();

        $cycle = $contract->activeBillingCycle;

        $tickets = Ticket::recurring()
            ->where('client_id', $client->id)
            ->when($cycle, fn($q) => $q->where('billing_cycle_id', $cycle->id))
            ->with(['assigned', 'deliverableCredit.contractService'])
            ->latest('id')
            ->get();

        $credits = ($cycle?->credits ?? collect())->map(function (DeliverableCredit $credit) use ($tickets) {
            $cs = $credit->contractService;
            $serviceTickets = $tickets->where('deliverable_credit_id', $credit->id);

            return [
                'id'           => $credit->id,
                'name'         => $cs?->name,
                'prefix'       => $cs?->prefix,
                'color'        => $cs?->color,
                'unit_type'    => $cs?->unit_type,
                'is_unlimited' => (bool) $credit->is_unlimited,
                'total'        => (int) $credit->total,
                'rolled_over'  => (int) $credit->rolled_over,
                'capacity'     => (int) $credit->total + (int) $credit->rolled_over,
                'consumed'     => (int) $credit->consumed,
                'remaining'    => $credit->is_unlimited
                    ? null
                    : max(0, ($credit->total + $credit->rolled_over) - $credit->consumed),
                'by_status'    => [
                    'Nuevos'      => $serviceTickets->where('status', 'Nuevos')->count(),
                    'En Proceso'  => $serviceTickets->where('status', 'En Proceso')->count(),
                    'En Revisión' => $serviceTickets->where('status', 'En Revisión')->count(),
                    'Ajustes'     => $serviceTickets->where('status', 'Ajustes')->count(),
                    'Completados' => $serviceTickets->where('status', 'Completados')->count(),
                ],
            ];
        })->values();

        // Historial breve de ciclos (últimos 6)
        $history = $contract->billingCycles()
            ->withCount(['tickets', 'tickets as completed_count' => fn($q) => $q->where('status', 'Completados')])
            ->limit(6)
            ->get(['id', 'period_start', 'period_end', 'status'])
            ->map(fn($c) => [
                'id'              => $c->id,
                'label'           => $c->period_start->format('M Y'),
                'status'          => $c->status,
                'tickets_count'   => (int) $c->tickets_count,
                'completed_count' => (int) $c->completed_count,
            ]);

        return Inertia::render('Recurring/ClientShow', [
            'client'   => [
                'id'            => $client->id,
                'business_name' => $client->business_name,
                'contact_name'  => $client->contact_name,
                'email'         => $client->email,
            ],
            'contract' => [
                'id'              => $contract->id,
                'contract_number' => $contract->contract_number,
                'start_date'      => $contract->start_date,
                'end_date'        => $contract->end_date,
                'monthly_amount'  => $contract->monthly_amount,
            ],
            'cycle' => $cycle ? [
                'id'           => $cycle->id,
                'period_start' => $cycle->period_start->toDateString(),
                'period_end'   => $cycle->period_end->toDateString(),
                'label'        => $cycle->period_start->format('M Y'),
                'status'       => $cycle->status,
            ] : null,
            'credits' => $credits,
            'tickets' => $tickets,
            'history' => $history,
        ]);
    }

    /**
     * Abre manualmente el ciclo del mes para un contrato (botón "Abrir ciclo").
     */
    public function openCycle(Client $client, OpenBillingCycle $openCycle)
    {
        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->latest('id')
            ->firstOrFail();

        $cycle = $openCycle($contract);

        return back()->with('success', sprintf(
            'Ciclo %s abierto correctamente.',
            $cycle->period_start->format('M Y')
        ));
    }

    /**
     * Crea un ticket on-demand (no fijo) dentro del ciclo activo.
     * Si hay un crédito de tipo on_demand_pool con capacidad, lo descuenta.
     */
    public function createDeliverable(Request $request, Client $client)
    {
        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'priority'              => 'required|string|in:Baja,Media,Alta,Urgente',
            'content'               => 'nullable|string',
            'due_date'              => 'nullable|date',
            'deliverable_credit_id' => 'nullable|exists:deliverable_credits,id',
            'assigned_id'           => 'nullable|exists:users,id',
        ]);

        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->latest('id')
            ->firstOrFail();

        $cycle = $contract->activeBillingCycle;
        abort_if(!$cycle, 422, 'No hay un ciclo activo. Abre el ciclo del mes antes de crear entregables.');

        return DB::transaction(function () use ($data, $client, $contract, $cycle) {
            $credit = null;
            $sequence = null;

            if (!empty($data['deliverable_credit_id'])) {
                $credit = DeliverableCredit::with('contractService')
                    ->where('billing_cycle_id', $cycle->id)
                    ->findOrFail($data['deliverable_credit_id']);

                if (!$credit->hasCapacity()) {
                    abort(422, 'Este crédito ya no tiene capacidad disponible este mes.');
                }

                $sequence = Ticket::where('deliverable_credit_id', $credit->id)->max('sequence_number') + 1;
            }

            $ticket = Ticket::create([
                'title'                 => $data['title'],
                'priority'              => $data['priority'],
                'content'               => $data['content'] ?? null,
                'status'                => 'Nuevos',
                'source_type'           => Ticket::SOURCE_RECURRING,
                'creator_id'            => Auth::id(),
                'assigned_id'           => $data['assigned_id'] ?? null,
                'client_id'             => $client->id,
                'billing_cycle_id'      => $cycle->id,
                'deliverable_credit_id' => $credit?->id,
                'sequence_number'       => $sequence,
                'due_date'              => $data['due_date'] ?? null,
            ]);

            // El contador `consumed` se recalcula automáticamente vía model event en Ticket.

            return back()->with('success', 'Entregable creado.');
        });
    }
}
