<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'period_start',
        'period_end',
        'status',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'closed_at'    => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(DeliverableCredit::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
