<?php

namespace App\Http\Controllers;

use App\Actions\Recurring\OpenBillingCycle;
use App\Models\Contract;
use App\Models\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractServiceController extends Controller
{
    public function store(Request $request, Contract $contract)
    {
        $data = $this->validated($request);

        $contract->contractServices()->create($data);

        return back()->with('success', 'Entregable agregado al contrato.');
    }

    public function update(Request $request, Contract $contract, ContractService $service)
    {
        abort_if($service->contract_id !== $contract->id, 404);

        $data = $this->validated($request);
        $service->update($data);

        return back()->with('success', 'Entregable actualizado.');
    }

    public function destroy(Contract $contract, ContractService $service)
    {
        abort_if($service->contract_id !== $contract->id, 404);

        // Si tiene créditos con tickets vinculados al ciclo activo, evitar borrar.
        $hasTickets = DB::table('tickets')
            ->whereIn('deliverable_credit_id', $service->credits()->pluck('id'))
            ->exists();

        if ($hasTickets) {
            return back()->withErrors(['service' => 'No se puede eliminar: ya hay tickets generados desde este entregable. Pausa el contrato o ajusta la cuota.']);
        }

        $service->credits()->delete();
        $service->delete();

        return back()->with('success', 'Entregable eliminado.');
    }

    /**
     * Abre manualmente el ciclo mensual de este contrato (botón "Abrir ciclo del mes").
     */
    public function openCycle(Contract $contract, OpenBillingCycle $openCycle)
    {
        abort_if(!$contract->contractServices()->exists(), 422, 'El contrato no tiene entregables recurrentes configurados.');

        $cycle = $openCycle($contract);

        return back()->with('success', sprintf('Ciclo %s abierto.', $cycle->period_start->format('M Y')));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                => 'required|string|max:255',
            'prefix'              => 'required|string|max:8',
            'color'               => 'nullable|string|max:16',
            'quantity_per_cycle'  => 'required|integer|min:0|max:999',
            'unit_type'           => 'required|in:fixed,on_demand_pool,unlimited',
            'rollover_allowed'    => 'boolean',
            'auto_create_tickets' => 'boolean',
            'sort_order'          => 'nullable|integer',
            'service_id'          => 'nullable|exists:services,id',
            'service_addon_id'    => 'nullable|exists:service_addons,id',
            'client_service_id'   => 'nullable|exists:client_services,id',
        ]);
    }
}
