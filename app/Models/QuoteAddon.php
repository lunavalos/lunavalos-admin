<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteAddon extends Model
{
    protected $fillable = [
        'quote_id',
        'service_addon_id',
        'quantity',
        'unit_price',
        'billing_cycle',
        'billing_cycle_months',
        'is_required',
    ];

    protected $casts = [
        'quantity'             => 'integer',
        'unit_price'           => 'decimal:2',
        'billing_cycle_months' => 'integer',
        'is_required'          => 'boolean',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function serviceAddon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class);
    }

    public function getLineTotalAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_price);
    }
}
