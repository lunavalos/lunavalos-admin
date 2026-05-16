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
        $services = Service::with(['services', 'costs', 'features'])->get();
        return Inertia::render('Services/Index', [
            'services' => $services,
        ]);
    }

    public function create()
    {
        $availableServices = Service::with('costs')
            ->where('is_package', false)
            ->orderBy('name')
            ->get();
        return Inertia::render('Services/Create', [
            'availableServices' => $availableServices,
            'addonCategories'   => config('service_addons.categories'),
            'maxPaymentMonths'  => config('quotes.max_payment_plan_months', 24),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request);

        $service = Service::create($data['fields']);

        if ($service->is_package && !empty($data['services'])) {
            $service->services()->sync($data['services']);
        }

        $this->syncCosts($service, $data['costs']);
        $this->syncFeatures($service, $data['features']);

        return redirect()->route('services.index')->with('message', 'Servicio creado exitosamente.');
    }

    public function edit(Service $service)
    {
        $service->load(['services.costs', 'costs', 'features']);
        $availableServices = Service::with('costs')
            ->where('is_package', false)
            ->where('id', '!=', $service->id)
            ->orderBy('name')
            ->get();
        return Inertia::render('Services/Edit', [
            'service'           => $service,
            'availableServices' => $availableServices,
            'addonCategories'   => config('service_addons.categories'),
            'maxPaymentMonths'  => config('quotes.max_payment_plan_months', 24),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validatedPayload($request);

        $service->update($data['fields']);

        if ($service->is_package && !empty($data['services'])) {
            $service->services()->sync($data['services']);
        } else {
            $service->services()->detach();
        }

        $this->syncCosts($service, $data['costs']);
        $this->syncFeatures($service, $data['features']);

        return redirect()->route('services.index')->with('message', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index')->with('message', 'Servicio eliminado exitosamente.');
    }

    /**
     * Valida la request y separa los pedazos del payload.
     */
    private function validatedPayload(Request $request): array
    {
        $addonCategories = array_keys(config('service_addons.categories', []));
        $maxMonths       = (int) config('quotes.max_payment_plan_months', 24);

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'description'                   => 'nullable|string',
            'price'                         => 'required|numeric|min:0',
            'renewal_price'                 => 'nullable|numeric|min:0',
            'billing_type'                  => 'required|in:unique,monthly,annual',
            'is_package'                    => 'boolean',
            'required_addon_categories'     => 'nullable|array',
            'required_addon_categories.*'   => ['string', \Illuminate\Validation\Rule::in($addonCategories)],
            'payment_plan_months'           => ['nullable', 'integer', 'min:1', 'max:' . $maxMonths],
            'services'                      => 'nullable|array',
            'services.*'                    => 'integer|exists:services,id',
            'costs'                         => 'nullable|array',
            'costs.*.title'                 => 'required|string',
            'costs.*.quantity'              => 'required|integer|min:1',
            'costs.*.price'                 => 'required|numeric|min:0',
            'features'                      => 'nullable|array',
            'features.*.label'              => 'required|string|max:255',
        ]);

        $categories = array_values(array_unique($validated['required_addon_categories'] ?? []));

        return [
            'fields' => [
                'name'                       => $validated['name'],
                'description'                => $validated['description'] ?? null,
                'price'                      => $validated['price'],
                'renewal_price'              => $validated['renewal_price'] ?? null,
                'billing_type'               => $validated['billing_type'],
                'is_package'                 => $validated['is_package'] ?? false,
                // Mantenemos la columna heredada en sync con el primer valor del array.
                'required_addon_category'    => $categories[0] ?? null,
                'required_addon_categories'  => $categories,
                'payment_plan_months'        => $validated['payment_plan_months'] ?? 1,
            ],
            'services' => $validated['services'] ?? [],
            'costs'    => $validated['costs'] ?? [],
            'features' => $validated['features'] ?? [],
        ];
    }

    private function syncCosts(Service $service, array $costs): void
    {
        $service->costs()->delete();
        foreach ($costs as $cost) {
            $service->costs()->create($cost);
        }
    }

    private function syncFeatures(Service $service, array $features): void
    {
        $service->features()->delete();
        foreach (array_values($features) as $index => $feature) {
            $service->features()->create([
                'label'      => $feature['label'],
                'sort_order' => $feature['sort_order'] ?? $index,
            ]);
        }
    }
}
