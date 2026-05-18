<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverableCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_cycle_id',
        'contract_service_id',
        'total',
        'rolled_over',
        'consumed',
        'is_unlimited',
    ];

    protected $casts = [
        'total'        => 'integer',
        'rolled_over'  => 'integer',
        'consumed'     => 'integer',
        'is_unlimited' => 'boolean',
    ];

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function contractService(): BelongsTo
    {
        return $this->belongsTo(ContractService::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function getCapacityAttribute(): int
    {
        return $this->total + $this->rolled_over;
    }

    public function getRemainingAttribute(): int
    {
        if ($this->is_unlimited) {
            return PHP_INT_MAX;
        }
        return max(0, $this->capacity - $this->consumed);
    }

    public function getPercentConsumedAttribute(): int
    {
        if ($this->is_unlimited || $this->capacity === 0) {
            return 0;
        }
        return (int) round(($this->consumed / $this->capacity) * 100);
    }

    public function hasCapacity(): bool
    {
        return $this->is_unlimited || $this->remaining > 0;
    }
}
