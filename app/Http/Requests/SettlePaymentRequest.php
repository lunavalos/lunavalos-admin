<?php

namespace App\Http\Requests;

use App\Models\ClientPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettlePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Registrar Pagos')
            || $this->user()?->hasAnyRole(['Administrador', 'Administrador Master']);
    }

    public function rules(): array
    {
        return [
            'amount'         => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(ClientPayment::METHODS)],
            'reference'      => ['nullable', 'string', 'max:100'],
            'paid_at'        => ['required', 'date'],
            'evidence_file'  => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
