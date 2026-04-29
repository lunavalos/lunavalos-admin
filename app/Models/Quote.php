<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'client_id',
        'client_name',
        'contact_name',
        'phone',
        'email',
        'status',
        'issue_date',
        'valid_until',
        'duration',
        'notes',
        'include_payment_terms',
        'is_multiple_choice'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'include_payment_terms' => 'boolean',
        'is_multiple_choice' => 'boolean'
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

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
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
