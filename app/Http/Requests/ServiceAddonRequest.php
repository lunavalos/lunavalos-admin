<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización vía middleware (can:Crear Addons / Editar Addons)
    }

    public function rules(): array
    {
        $categories     = array_keys(config('service_addons.categories', []));
        $billingCycles  = array_keys(config('service_addons.billing_cycles', []));

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'category'             => ['required', 'string', Rule::in($categories)],
            'description'          => ['nullable', 'string'],
            'price'                => ['required', 'numeric', 'min:0'],
            'billing_cycle'        => ['required', 'string', Rule::in($billingCycles)],
            'billing_cycle_months' => [
                'nullable',
                'integer',
                'min:1',
                'max:60',
                Rule::requiredIf(fn () => $this->input('billing_cycle') === 'custom_months'),
            ],
            'is_active'            => ['boolean'],
            'costs'                => ['nullable', 'array'],
            'costs.*.title'        => ['required', 'string', 'max:255'],
            'costs.*.quantity'     => ['required', 'integer', 'min:1'],
            'costs.*.price'        => ['required', 'numeric', 'min:0'],
        ];
    }
}
