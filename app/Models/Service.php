<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'renewal_price',
        'currency',
        'billing_type',
        'is_package',
        'required_addon_category',
        'required_addon_categories',
        'payment_plan_months',
    ];

    protected $casts = [
        'is_package'                => 'boolean',
        'price'                     => 'decimal:2',
        'renewal_price'             => 'decimal:2',
        'payment_plan_months'       => 'integer',
        'required_addon_categories' => 'array',
    ];

    protected $appends = [
        'required_addon_categories_list',
    ];

    /**
     * Devuelve siempre un arreglo (lee primero el JSON nuevo y, si está vacío,
     * cae al valor heredado single-string para retro-compat).
     */
    public function getRequiredAddonCategoriesListAttribute(): array
    {
        $list = $this->required_addon_categories;
        if (is_array($list) && count($list) > 0) {
            return array_values(array_filter($list, fn ($v) => $v !== null && $v !== ''));
        }
        if (! empty($this->required_addon_category)) {
            return [$this->required_addon_category];
        }
        return [];
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'package_service', 'package_id', 'service_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Service::class, 'package_service', 'service_id', 'package_id');
    }

    public function costs()
    {
        return $this->hasMany(ServiceCost::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(ServiceFeature::class)->orderBy('sort_order');
    }
}
