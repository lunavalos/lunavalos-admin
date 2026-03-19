<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ServiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Servicios', only: ['index', 'show']),
            new Middleware('can:Crear Servicios', only: ['create', 'store']),
            new Middleware('can:Editar Servicios', only: ['edit', 'update']),
            new Middleware('can:Eliminar Servicios', only: ['destroy']),
        ];
    }

    public function index()
    {
        $services = Service::with(['services', 'costs'])->get();
        return Inertia::render('Services/Index', [
            'services' => $services
        ]);
    }

    public function create()
    {
        $availableServices = Service::where('is_package', false)->get();
        return Inertia::render('Services/Create', [
            'availableServices' => $availableServices
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'renewal_price' => 'nullable|numeric|min:0',
            'billing_type' => 'required|in:unique,monthly',
            'is_package' => 'boolean',
            'services' => 'nullable|array',
            'costs' => 'nullable|array',
            'costs.*.title' => 'required|string',
            'costs.*.quantity' => 'required|integer|min:1',
            'costs.*.price' => 'required|numeric|min:0',
        ]);

        $service = Service::create($request->only('name', 'description', 'price', 'renewal_price', 'billing_type', 'is_package'));

        if ($request->is_package && $request->services) {
            $service->services()->sync($request->services);
        }

        if ($request->costs) {
            foreach ($request->costs as $cost) {
                $service->costs()->create($cost);
            }
        }

        return redirect()->route('services.index')->with('message', 'Servicio creado exitosamente.');
    }

    public function edit(Service $service)
    {
        $service->load(['services', 'costs']);
        $availableServices = Service::where('is_package', false)->where('id', '!=', $service->id)->get();
        return Inertia::render('Services/Edit', [
            'service' => $service,
            'availableServices' => $availableServices
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'renewal_price' => 'nullable|numeric|min:0',
            'billing_type' => 'required|in:unique,monthly',
            'is_package' => 'boolean',
            'services' => 'nullable|array',
            'costs' => 'nullable|array',
            'costs.*.title' => 'required|string',
            'costs.*.quantity' => 'required|integer|min:1',
            'costs.*.price' => 'required|numeric|min:0',
        ]);

        $service->update($request->only('name', 'description', 'price', 'renewal_price', 'billing_type', 'is_package'));

        if ($request->is_package && $request->services) {
            $service->services()->sync($request->services);
        } else {
            $service->services()->detach();
        }

        // Update costs
        $service->costs()->delete();
        if ($request->costs) {
            foreach ($request->costs as $cost) {
                $service->costs()->create($cost);
            }
        }

        return redirect()->route('services.index')->with('message', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index')->with('message', 'Servicio eliminado exitosamente.');
    }
}
