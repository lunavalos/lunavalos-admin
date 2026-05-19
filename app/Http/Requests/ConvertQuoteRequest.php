<?php

namespace App\Http\Requests;

use App\Models\ClientPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Crear Cotizaciones')
            || (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            // Datos fiscales (opcionales, sobrescriben los del cliente).
            'legal_name'           => ['nullable', 'string', 'max:255'],
            'rfc'                  => ['nullable', 'string', 'max:20'],
            'tax_regime'           => ['nullable', 'string', Rule::in(array_keys(config('sat.tax_regimes')))],
            'fiscal_address'       => ['nullable', 'string', 'max:500'],
            'tax_zip_code'         => ['nullable', 'string', 'max:10'],
            'legal_representative' => ['nullable', 'string', 'max:255'],

            // Contrato.
            'start_date'           => ['nullable', 'date'],
            'notes'                => ['nullable', 'string', 'max:2000'],

            // Anticipo.
            'anticipo_amount'      => ['required', 'numeric', 'min:0'],
            'payment_method'       => ['required_with:anticipo_amount', 'string', Rule::in(ClientPayment::METHODS)],
            'payment_reference'    => ['nullable', 'string', 'max:100'],
            'paid_at'              => ['nullable', 'date'],
            'payment_notes'        => ['nullable', 'string', 'max:1000'],
            'evidence_file'        => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ];
    }
}
