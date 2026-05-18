<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractService extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'service_id',
        'service_addon_id',
        'name',
        'prefix',
        'color',
        'quantity_per_cycle',
        'unit_type',
        'rollover_allowed',
        'auto_create_tickets',
        'sort_order',
    ];

    protected $casts = [
        'quantity_per_cycle'   => 'integer',
        'rollover_allowed'     => 'boolean',
        'auto_create_tickets'  => 'boolean',
        'sort_order'           => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceAddon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(DeliverableCredit::class);
    }

    public function isFixed(): bool
    {
        return $this->unit_type === 'fixed';
    }

    public function isOnDemandPool(): bool
    {
        return $this->unit_type === 'on_demand_pool';
    }

    public function isUnlimited(): bool
    {
        return $this->unit_type === 'unlimited';
    }
}
