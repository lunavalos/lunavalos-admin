<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceAddonRequest;
use App\Models\ServiceAddon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ServiceAddonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Addons',      only: ['index', 'show']),
            new Middleware('can:Crear Addons',    only: ['create', 'store']),
            new Middleware('can:Editar Addons',   only: ['edit', 'update']),
            new Middleware('can:Eliminar Addons', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $addons = ServiceAddon::orderBy('category')->orderBy('name')->get();

        return Inertia::render('Services/Addons/Index', [
            'addons'     => $addons,
            'categories' => config('service_addons.categories'),
            'cycles'     => config('service_addons.billing_cycles'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Services/Addons/Create', [
            'categories' => config('service_addons.categories'),
            'cycles'     => config('service_addons.billing_cycles'),
        ]);
    }

    public function store(ServiceAddonRequest $request): RedirectResponse
    {
        $data  = $request->validated();
        $costs = $data['costs'] ?? [];
        unset($data['costs']);

        $addon = ServiceAddon::create($data);
        $this->syncCosts($addon, $costs);

        return redirect()
            ->route('service-addons.index')
            ->with('message', 'Servicio adicional creado correctamente.');
    }

    public function edit(ServiceAddon $serviceAddon): Response
    {
        $serviceAddon->load('costs');

        return Inertia::render('Services/Addons/Edit', [
            'addon'      => $serviceAddon,
            'categories' => config('service_addons.categories'),
            'cycles'     => config('service_addons.billing_cycles'),
        ]);
    }

    public function update(ServiceAddonRequest $request, ServiceAddon $serviceAddon): RedirectResponse
    {
        $data  = $request->validated();
        $costs = $data['costs'] ?? [];
        unset($data['costs']);

        $serviceAddon->update($data);
        $this->syncCosts($serviceAddon, $costs);

        return redirect()
            ->route('service-addons.index')
            ->with('message', 'Servicio adicional actualizado.');
    }

    public function destroy(ServiceAddon $serviceAddon): RedirectResponse
    {
        $serviceAddon->delete();

        return redirect()
            ->route('service-addons.index')
            ->with('message', 'Servicio adicional eliminado.');
    }

    private function syncCosts(ServiceAddon $addon, array $costs): void
    {
        $addon->costs()->delete();
        foreach ($costs as $cost) {
            $addon->costs()->create([
                'title'    => $cost['title'],
                'quantity' => $cost['quantity'],
                'price'    => $cost['price'],
            ]);
        }
    }
}
