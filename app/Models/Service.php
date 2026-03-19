<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'renewal_price',
        'billing_type',
        'is_package'
    ];

    protected $casts = [
        'is_package' => 'boolean',
    ];

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
}
