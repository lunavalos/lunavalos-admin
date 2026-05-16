<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'client_id',
        'package_service_id',
        'package_payment_plan_months',
        'client_name',
        'contact_name',
        'phone',
        'email',
        'status',
        'issue_date',
        'valid_until',
        'duration',
        'subtotal',
        'discount_amount',
        'tax_regime',
        'applies_iva',
        'iva_rate',
        'iva_amount',
        'applies_isr_retention',
        'isr_retention_rate',
        'isr_retention_amount',
        'applies_iva_retention',
        'iva_retention_rate',
        'iva_retention_amount',
        'total',
        'notes',
        'observations',
        'include_payment_terms',
        'is_multiple_choice',
        'converted_at',
        'converted_by_user_id',
        'applies_iva'                 => 'boolean',
        'iva_rate'                    => 'decimal:4',
        'iva_amount'                  => 'decimal:2',
        'applies_isr_retention'       => 'boolean',
        'isr_retention_rate'          => 'decimal:4',
        'isr_retention_amount'        => 'decimal:2',
        'applies_iva_retention'       => 'boolean',
        'iva_retention_rate'          => 'decimal:4',
        'iva_retention_amount'        => 'decimal:2',
        'legacy',
    ];

    protected $casts = [
        'issue_date'                  => 'date',
        'valid_until'                 => 'date',
        'converted_at'                => 'datetime',
        'subtotal'                    => 'decimal:2',
        'discount_amount'             => 'decimal:2',
        'total'                       => 'decimal:2',
        'package_payment_plan_months' => 'integer',
        'include_payment_terms'       => 'boolean',
        'is_multiple_choice'          => 'boolean',
        'legacy'                      => 'boolean',
    ];

    protected $appends = ['total_unique', 'total_monthly', 'total_annual'];

    public function getTotalUniqueAttribute()
    {
        return $this->items->where('billing_type', 'unique')->reduce(function ($carry, $item) {
            return $carry + ($item->quantity * $item->unit_price);
        }, 0);
    }

    public function getTotalMonthlyAttribute()
    {
        return $this->items->where('billing_type', 'monthly')->reduce(function ($carry, $item) {
            return $carry + ($item->quantity * $item->unit_price);
        }, 0);
    }

    public function getTotalAnnualAttribute()
    {
        return $this->items->where('billing_type', 'annual')->reduce(function ($carry, $item) {
            return $carry + ($item->quantity * $item->unit_price);
        }, 0);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(QuoteAddon::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'package_service_id');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    public function contract()
    {
        return $this->hasOne(Contract::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
