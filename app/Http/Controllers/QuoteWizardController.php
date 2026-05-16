<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteWizardRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteAddon;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Support\QuoteStateMachine;
use App\Support\QuoteTaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuoteWizardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Crear Cotizaciones', only: ['create', 'store']),
            new Middleware('can:Ver Cotizaciones',    only: ['show']),
            new Middleware('can:Editar Cotizaciones', only: ['transition', 'edit', 'update']),
        ];
    }

    /**
     * Pantalla del wizard. Devuelve el catálogo y datos iniciales.
     */
    public function create()
    {
        $services = Service::query()
            ->with([
                'features' => fn ($q) => $q->orderBy('sort_order'),
                'services:id,name,description,price,billing_type',
            ])
            ->orderBy('name')
            ->get([
                'id', 'name', 'description', 'price', 'renewal_price',
                'billing_type', 'is_package', 'required_addon_category',
                'required_addon_categories', 'payment_plan_months',
            ]);

        $addons = ServiceAddon::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $clients = Client::orderBy('business_name')
            ->get([
                'id', 'business_name', 'contact_name', 'email', 'phone',
                'rfc', 'tax_regime', 'cfdi_use', 'tax_zip_code',
                'fiscal_address', 'legal_name',
                'applies_iva', 'iva_rate',
                'applies_isr_retention', 'isr_retention_rate',
                'applies_iva_retention', 'iva_retention_rate',
            ]);

        return Inertia::render('Quotes/Wizard/Index', [
            'services'         => $services,
            'addons'           => $addons,
            'addonsByCategory' => $addons->groupBy('category')->map->values(),
            'clients'          => $clients,
            'categoryLabels'   => config('service_addons.categories'),
            'cycleLabels'      => config('service_addons.billing_cycles'),
            'taxRegimes'       => config('sat.tax_regimes'),
            'cfdiUses'         => config('sat.cfdi_uses'),
            'taxDefaults'      => config('sat.defaults'),
            'maxPaymentMonths' => (int) config('quotes.max_payment_plan_months', 24),
            'defaultValidity'  => (int) config('quotes.default_validity_days', 15),
        ]);
    }

    /**
     * Guarda la cotización con paquete + addons + items derivados,
     * congelando precios. Todo en una sola transacción.
     */
    public function store(StoreQuoteWizardRequest $request)
    {
        $data = $request->validated();

        $quote = DB::transaction(function () use ($data) {
            $service = Service::findOrFail($data['package_service_id']);

            // Precio del paquete (snapshot)
            $packagePrice = (float) $service->price;
            $subtotal     = $packagePrice;

            // Snapshots de addons
            $addonRows = [];
            foreach ($data['addons'] ?? [] as $row) {
                $addon = ServiceAddon::findOrFail($row['service_addon_id']);
                $qty   = (int) $row['quantity'];
                $line  = $qty * (float) $addon->price;
                $subtotal += $line;

                $addonRows[] = [
                    'service_addon_id'     => $addon->id,
                    'quantity'             => $qty,
                    'unit_price'           => $addon->price,
                    'billing_cycle'        => $addon->billing_cycle,
                    'billing_cycle_months' => $addon->billing_cycle_months ?? null,
                    'is_required'          => (bool) ($addon->is_required ?? false),
                ];
            }

            $discount     = (float) ($data['discount_amount'] ?? 0);
            $taxableBase  = max(0, $subtotal - $discount);

            $taxes = QuoteTaxCalculator::compute($taxableBase, [
                'applies_iva'            => $data['applies_iva']            ?? false,
                'iva_rate'               => $data['iva_rate']               ?? 0,
                'applies_isr_retention'  => $data['applies_isr_retention']  ?? false,
                'isr_retention_rate'     => $data['isr_retention_rate']     ?? 0,
                'applies_iva_retention'  => $data['applies_iva_retention']  ?? false,
                'iva_retention_rate'     => $data['iva_retention_rate']     ?? 0,
            ]);

            $quote = Quote::create([
                'client_id'                   => $data['client_id'] ?? null,
                'package_service_id'          => $service->id,
                'package_payment_plan_months' => $data['package_payment_plan_months'],
                'client_name'                 => $data['client_name'],
                'contact_name'                => $data['contact_name']  ?? null,
                'email'                       => $data['email']         ?? null,
                'phone'                       => $data['phone']         ?? null,
                'status'                      => $data['status']        ?? 'Borrador',
                'issue_date'                  => $data['issue_date'],
                'valid_until'                 => $data['valid_until'],
                'duration'                    => $data['duration']      ?? null,
                'subtotal'                    => $subtotal,
                'discount_amount'             => $discount,
                'tax_regime'                  => $data['tax_regime']    ?? null,
                'applies_iva'                 => $taxes['applies_iva'],
                'iva_rate'                    => $taxes['iva_rate'],
                'iva_amount'                  => $taxes['iva_amount'],
                'applies_isr_retention'       => $taxes['applies_isr_retention'],
                'isr_retention_rate'          => $taxes['isr_retention_rate'],
                'isr_retention_amount'        => $taxes['isr_retention_amount'],
                'applies_iva_retention'       => $taxes['applies_iva_retention'],
                'iva_retention_rate'          => $taxes['iva_retention_rate'],
                'iva_retention_amount'        => $taxes['iva_retention_amount'],
                'total'                       => $taxes['total'],
                'notes'                       => $data['notes']         ?? null,
                'observations'                => $data['observations']  ?? null,
                'include_payment_terms'       => (bool) ($data['include_payment_terms'] ?? true),
                'is_multiple_choice'          => false,
                'legacy'                      => false,
            ]);

            // QuoteItem espejo del paquete (compatibilidad con módulo legacy de PDFs/listados).
            QuoteItem::create([
                'quote_id'           => $quote->id,
                'service_id'         => $service->id,
                'concept'            => $service->name,
                'description'        => $service->description,
                'quantity'           => 1,
                'unit_price'         => $service->price,
                'unit_renewal_price' => $service->renewal_price,
                'billing_type'       => $service->billing_type ?? 'unique',
            ]);

            foreach ($addonRows as $row) {
                $quote->addons()->create($row);
            }

            return $quote;
        });

        return redirect()
            ->route('quotes.manage', $quote)
            ->with('success', 'Cotización creada correctamente.');
    }

    /**
     * Pantalla del wizard pre-cargada con los datos de la cotización.
     */
    public function edit(Quote $quote)
    {
        if ($quote->legacy) {
            return redirect()->route('quotes.edit', $quote)
                ->with('warning', 'Las cotizaciones legacy se editan en la vista clásica.');
        }

        if ($quote->contract) {
            return redirect()->route('quotes.manage', $quote)
                ->withErrors(['edit' => 'No se puede editar una cotización con contrato generado.']);
        }

        $quote->load(['addons', 'items', 'client']);

        $services = Service::query()
            ->with([
                'features' => fn ($q) => $q->orderBy('sort_order'),
                'services:id,name,description,price,billing_type',
            ])
            ->orderBy('name')
            ->get([
                'id', 'name', 'description', 'price', 'renewal_price',
                'billing_type', 'is_package', 'required_addon_category',
                'required_addon_categories', 'payment_plan_months',
            ]);

        $addons = ServiceAddon::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $clients = Client::orderBy('business_name')
            ->get([
                'id', 'business_name', 'contact_name', 'email', 'phone',
                'rfc', 'tax_regime', 'cfdi_use', 'tax_zip_code',
                'fiscal_address', 'legal_name',
                'applies_iva', 'iva_rate',
                'applies_isr_retention', 'isr_retention_rate',
                'applies_iva_retention', 'iva_retention_rate',
            ]);

        return Inertia::render('Quotes/Wizard/Index', [
            'quote'            => $quote,
            'services'         => $services,
            'addons'           => $addons,
            'addonsByCategory' => $addons->groupBy('category')->map->values(),
            'clients'          => $clients,
            'categoryLabels'   => config('service_addons.categories'),
            'cycleLabels'      => config('service_addons.billing_cycles'),
            'taxRegimes'       => config('sat.tax_regimes'),
            'cfdiUses'         => config('sat.cfdi_uses'),
            'taxDefaults'      => config('sat.defaults'),
            'maxPaymentMonths' => (int) config('quotes.max_payment_plan_months', 24),
            'defaultValidity'  => (int) config('quotes.default_validity_days', 15),
        ]);
    }

    /**
     * Actualiza una cotización existente reutilizando la misma validación del store.
     * Reemplaza el QuoteItem espejo y todos los addons.
     */
    public function update(StoreQuoteWizardRequest $request, Quote $quote)
    {
        if ($quote->legacy) {
            return back()->withErrors(['edit' => 'Las cotizaciones legacy son de solo lectura.']);
        }
        if ($quote->contract) {
            return back()->withErrors(['edit' => 'No se puede editar una cotización con contrato generado.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $quote) {
            $service = Service::findOrFail($data['package_service_id']);

            $packagePrice = (float) $service->price;
            $subtotal     = $packagePrice;

            $addonRows = [];
            foreach ($data['addons'] ?? [] as $row) {
                $addon = ServiceAddon::findOrFail($row['service_addon_id']);
                $qty   = (int) $row['quantity'];
                $line  = $qty * (float) $addon->price;
                $subtotal += $line;

                $addonRows[] = [
                    'service_addon_id'     => $addon->id,
                    'quantity'             => $qty,
                    'unit_price'           => $addon->price,
                    'billing_cycle'        => $addon->billing_cycle,
                    'billing_cycle_months' => $addon->billing_cycle_months ?? null,
                    'is_required'          => (bool) ($addon->is_required ?? false),
                ];
            }

            $discount    = (float) ($data['discount_amount'] ?? 0);
            $taxableBase = max(0, $subtotal - $discount);

            $taxes = QuoteTaxCalculator::compute($taxableBase, [
                'applies_iva'            => $data['applies_iva']            ?? false,
                'iva_rate'               => $data['iva_rate']               ?? 0,
                'applies_isr_retention'  => $data['applies_isr_retention']  ?? false,
                'isr_retention_rate'     => $data['isr_retention_rate']     ?? 0,
                'applies_iva_retention'  => $data['applies_iva_retention']  ?? false,
                'iva_retention_rate'     => $data['iva_retention_rate']     ?? 0,
            ]);

            $quote->update([
                'client_id'                   => $data['client_id'] ?? null,
                'package_service_id'          => $service->id,
                'package_payment_plan_months' => $data['package_payment_plan_months'],
                'client_name'                 => $data['client_name'],
                'contact_name'                => $data['contact_name']  ?? null,
                'email'                       => $data['email']         ?? null,
                'phone'                       => $data['phone']         ?? null,
                'status'                      => $data['status']        ?? $quote->status,
                'issue_date'                  => $data['issue_date'],
                'valid_until'                 => $data['valid_until'],
                'duration'                    => $data['duration']      ?? null,
                'subtotal'                    => $subtotal,
                'discount_amount'             => $discount,
                'tax_regime'                  => $data['tax_regime']    ?? null,
                'applies_iva'                 => $taxes['applies_iva'],
                'iva_rate'                    => $taxes['iva_rate'],
                'iva_amount'                  => $taxes['iva_amount'],
                'applies_isr_retention'       => $taxes['applies_isr_retention'],
                'isr_retention_rate'          => $taxes['isr_retention_rate'],
                'isr_retention_amount'        => $taxes['isr_retention_amount'],
                'applies_iva_retention'       => $taxes['applies_iva_retention'],
                'iva_retention_rate'          => $taxes['iva_retention_rate'],
                'iva_retention_amount'        => $taxes['iva_retention_amount'],
                'total'                       => $taxes['total'],
                'notes'                       => $data['notes']         ?? null,
                'observations'                => $data['observations']  ?? null,
                'include_payment_terms'       => (bool) ($data['include_payment_terms'] ?? true),
            ]);

            // Reemplaza items y addons.
            $quote->items()->delete();
            $quote->addons()->delete();

            QuoteItem::create([
                'quote_id'           => $quote->id,
                'service_id'         => $service->id,
                'concept'            => $service->name,
                'description'        => $service->description,
                'quantity'           => 1,
                'unit_price'         => $service->price,
                'unit_renewal_price' => $service->renewal_price,
                'billing_type'       => $service->billing_type ?? 'unique',
            ]);

            foreach ($addonRows as $row) {
                $quote->addons()->create($row);
            }
        });

        return redirect()
            ->route('quotes.manage', $quote)
            ->with('success', 'Cotización actualizada correctamente.');
    }

    /**
     * Cambio de estado validado por la máquina de estados.
     */
    public function transition(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(config('quotes.statuses'))],
        ]);

        if ($quote->legacy) {
            return back()->withErrors(['status' => 'Las cotizaciones legacy son de solo lectura.']);
        }

        try {
            QuoteStateMachine::transition($quote, $data['status'], $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Estado actualizado a {$data['status']}.");
    }
}
