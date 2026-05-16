<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAddon extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'price',
        'billing_cycle',
        'billing_cycle_months',
        'is_active',
    ];

    protected $casts = [
        'price'                => 'decimal:2',
        'billing_cycle_months' => 'integer',
        'is_active'            => 'boolean',
    ];

    protected $appends = ['category_label', 'billing_cycle_label'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ServiceAddonCost::class);
    }

    public function scopeOfCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    public function getCategoryLabelAttribute(): string
    {
        return config("service_addons.categories.{$this->category}", $this->category);
    }

    public function getBillingCycleLabelAttribute(): string
    {
        $base = config("service_addons.billing_cycles.{$this->billing_cycle}", $this->billing_cycle);

        if ($this->billing_cycle === 'custom_months' && $this->billing_cycle_months) {
            return "Cada {$this->billing_cycle_months} meses";
        }

        return $base;
    }
}
