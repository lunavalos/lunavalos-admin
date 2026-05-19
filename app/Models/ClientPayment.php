<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPayment extends Model
{
    protected $fillable = [
        'client_id',
        'contract_id',
        'quote_id',
        'registered_by_user_id',
        'type',
        'status',
        'concept',
        'amount',
        'currency',
        'exchange_rate',
        'payment_method',
        'reference',
        'paid_at',
        'due_date',
        'installment_number',
        'evidence_file_path',
        'notes',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'exchange_rate'      => 'decimal:8',
        'paid_at'            => 'date:Y-m-d',
        'due_date'           => 'date:Y-m-d',
        'installment_number' => 'integer',
    ];

    /**
     * Importe normalizado a la moneda base usando el snapshot de FX.
     * Útil para KPIs/reportes. NUNCA sumes `amount` directo entre divisas.
     */
    public function getAmountInBaseAttribute(): float
    {
        return round((float) $this->amount * (float) ($this->exchange_rate ?: 1), 2);
    }

    public const TYPES    = ['anticipo', 'mensualidad', 'pago_unico', 'ajuste', 'reembolso'];
    public const METHODS  = ['efectivo', 'transferencia', 'tarjeta', 'cheque', 'otro'];
    public const STATUSES = ['programado', 'registrado', 'conciliado', 'facturado', 'cancelado'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
