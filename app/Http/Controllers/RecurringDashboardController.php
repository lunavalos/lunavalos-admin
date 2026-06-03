<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RecurringDashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Recurrentes'),
        ];
    }

    /**
     * Lista todos los clientes con planes mensuales activos + resumen de su ciclo actual.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isClient()) {
            if (!$user->client_id) {
                abort(403, 'El usuario cliente no tiene un cliente asignado.');
            }
            return redirect()->route('recurring.clients.show', $user->client_id);
        }
        $contracts = Contract::query()
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->with([
                'client:id,business_name,contact_name',
                'contractServices',
                'activeBillingCycle.credits.contractService',
                'activeBillingCycle.tickets',
            ])
            ->orderBy('contract_number')
            ->get();

        $rows = $contracts->map(function (Contract $contract) {
            $cycle   = $contract->activeBillingCycle;
            $credits = $cycle?->credits ?? collect();
            $tickets = $cycle?->tickets ?? collect();

            // Resumen por servicio
            $services = $credits->map(function ($credit) use ($tickets) {
                $cs = $credit->contractService;
                $serviceTickets = $tickets->where('deliverable_credit_id', $credit->id);

                return [
                    'id'             => $credit->id,
                    'name'           => $cs?->name,
                    'prefix'         => $cs?->prefix,
                    'color'          => $cs?->color,
                    'unit_type'      => $cs?->unit_type,
                    'is_unlimited'   => (bool) $credit->is_unlimited,
                    'total'          => (int) $credit->total,
                    'rolled_over'    => (int) $credit->rolled_over,
                    'capacity'       => (int) $credit->total + (int) $credit->rolled_over,
                    'consumed'       => (int) $credit->consumed,
                    'remaining'      => $credit->is_unlimited
                        ? null
                        : max(0, ($credit->total + $credit->rolled_over) - $credit->consumed),
                    'tickets_count'  => $serviceTickets->count(),
                    'in_progress'    => $serviceTickets->where('status', 'En Proceso')->count(),
                    'completed'      => $serviceTickets->where('status', 'Completados')->count(),
                ];
            })->values();

            $totalTickets    = $tickets->count();
            $overdueCount    = $tickets->filter(fn($t) => $t->due_date && $t->due_date->isPast() && $t->status !== 'Completados')->count();
            $inReviewCount   = $tickets->where('status', 'En Revisión')->count();

            return [
                'contract_id'      => $contract->id,
                'contract_number'  => $contract->contract_number,
                'client_id'        => $contract->client_id,
                'client_name'      => $contract->client?->business_name,
                'cycle_id'         => $cycle?->id,
                'cycle_label'      => $cycle?->period_start?->format('M Y'),
                'cycle_status'     => $cycle?->status,
                'services'         => $services,
                'totals'           => [
                    'tickets'   => $totalTickets,
                    'overdue'   => $overdueCount,
                    'in_review' => $inReviewCount,
                ],
            ];
        });

        return Inertia::render('Recurring/Dashboard', [
            'rows' => $rows,
        ]);
    }
}
