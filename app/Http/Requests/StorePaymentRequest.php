<?php

namespace App\Http\Requests;

use App\Models\ClientPayment;
use App\Models\Contract;
use App\Support\Money\CurrencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Registrar Pagos')
            || (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        /** @var CurrencyService $fx */
        $fx = app(CurrencyService::class);

        return [
            'client_id'      => ['required', 'exists:clients,id'],
            'contract_id'    => ['nullable', 'exists:contracts,id'],
            'type'           => ['required', Rule::in(ClientPayment::TYPES)],
            'concept'        => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'currency'       => ['nullable', 'string', 'size:3', Rule::in($fx->codes())],
            'payment_method' => ['required', Rule::in(ClientPayment::METHODS)],
            'reference'      => ['nullable', 'string', 'max:100'],
            'paid_at'        => ['required', 'date'],
            'evidence_file'  => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Regla cruzada: si el pago está asociado a un contrato, su moneda DEBE
     * coincidir con la del contrato. Esto evita mezclar divisas en la cobranza
     * de un mismo contrato (decisión contable confirmada con stakeholder).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $contractId = $this->input('contract_id');
            $currency   = strtoupper((string) ($this->input('currency') ?: ''));
            if (! $contractId || ! $currency) {
                return;
            }
            $contract = Contract::find($contractId);
            if (! $contract) {
                return;
            }
            $expected = strtoupper((string) ($contract->currency ?? config('currencies.default')));
            if ($currency !== $expected) {
                $v->errors()->add(
                    'currency',
                    "El pago debe registrarse en la moneda del contrato ({$expected}). Recibido: {$currency}."
                );
            }
        });
    }
}
