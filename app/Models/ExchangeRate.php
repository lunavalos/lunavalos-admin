<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro histórico de tipo de cambio (un par por fecha y fuente).
 * El consumo se hace por `App\Support\Money\CurrencyService`, no directamente.
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'rate_date',
        'from_currency',
        'to_currency',
        'rate',
        'source',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:8',
    ];
}
