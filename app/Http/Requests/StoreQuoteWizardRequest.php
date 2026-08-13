<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\ServiceAddon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StoreQuoteWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permisos verificados por middleware del controller.
    }

    /**
     * Retro-compat: el payload histórico enviaba un solo `package_service_id`.
     * Lo normalizamos al arreglo que usa el wizard multi-paquete.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('package_service_ids') && $this->filled('package_service_id')) {
            $this->merge(['package_service_ids' => [(int) $this->input('package_service_id')]]);
        }
    }

    public function rules(): array
    {
        $statuses = (array) config('quotes.statuses', []);
        $maxMonths = (int) config('quotes.max_payment_plan_months', 24);

        // Varios paquetes por cotización; el primero es el principal y queda en
        // `quotes.package_service_id`. El plan de pago es compartido por todos.
        return [
            'package_service_ids'          => ['required', 'array', 'min:1'],
            'package_service_ids.*'        => ['required', 'integer', 'distinct', 'exists:services,id'],
            'package_payment_plan_months'  => ['required', 'integer', 'min:1', "max:{$maxMonths}"],

            'client_id'                    => ['nullable', 'integer', 'exists:clients,id'],
            'client_name'                  => ['required', 'string', 'max:255'],
            'contact_name'                 => ['nullable', 'string', 'max:255'],
            'email'                        => ['nullable', 'email', 'max:255'],
            'phone'                        => ['nullable', 'string', 'max:50'],

            'issue_date'                   => ['required', 'date'],
            'valid_until'                  => ['required', 'date', 'after_or_equal:issue_date'],
            'duration'                     => ['nullable', 'string', 'max:255'],

            'discount_amount'              => ['nullable', 'numeric', 'min:0'],
            'notes'                        => ['nullable', 'string'],
            'observations'                 => ['nullable', 'string'],
            'include_payment_terms'        => ['boolean'],
            'status'                       => ['nullable', Rule::in($statuses)],

            // Moneda de la cotización (la conversión por línea se hace en el controller).
            'currency'                     => ['nullable', 'string', 'size:3', Rule::in(array_keys((array) config('currencies.supported', [])))],

            // Snapshot fiscal (opcionalmente heredado del cliente).
            'tax_regime'                   => ['nullable', Rule::in(array_keys(config('sat.tax_regimes')))],
            'applies_iva'                  => ['boolean'],
            'iva_rate'                     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'applies_isr_retention'        => ['boolean'],
            'isr_retention_rate'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'applies_iva_retention'        => ['boolean'],
            'iva_retention_rate'           => ['nullable', 'numeric', 'min:0', 'max:100'],

            'addons'                       => ['array'],
            'addons.*.service_addon_id'    => ['required', 'integer', 'exists:service_addons,id'],
            'addons.*.quantity'            => ['required', 'integer', 'min:1'],
            'addons.*.is_required'         => ['boolean'],
        ];
    }

    /**
     * Después de las reglas base, valida que se haya seleccionado al menos
     * un addon de cada categoría obligatoria exigida por los paquetes
     * elegidos (la unión de todos ellos, sin duplicar).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $services = Service::whereIn('id', (array) $this->input('package_service_ids', []))->get();
            if ($services->isEmpty()) {
                return;
            }

            $required = $services
                ->flatMap(fn (Service $s) => $s->required_addon_categories_list)
                ->unique()
                ->values()
                ->all();

            if (empty($required)) {
                return;
            }

            // Solo exigir categorías que tengan al menos un addon activo en el catálogo.
            $availableRequiredCategories = ServiceAddon::whereIn('category', $required)
                ->where('is_active', true)
                ->pluck('category')
                ->unique()
                ->all();

            $required = array_intersect($required, $availableRequiredCategories);

            if (empty($required)) {
                return;
            }

            $addons = $this->input('addons', []);
            $ids    = array_filter(array_map(fn ($a) => $a['service_addon_id'] ?? null, $addons));

            $selectedCategories = empty($ids)
                ? collect()
                : ServiceAddon::whereIn('id', $ids)
                    ->where('is_active', true)
                    ->pluck('category')
                    ->unique();

            $missing = array_values(array_diff($required, $selectedCategories->all()));
            if (! empty($missing)) {
                $labels = config('service_addons.categories', []);
                $human  = implode(', ', array_map(fn ($k) => $labels[$k] ?? $k, $missing));
                $v->errors()->add('addons', "Debes seleccionar al menos un addon de cada categoría obligatoria: {$human}.");
            }
        });
    }
}
