<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCost extends Model
{
    protected $fillable = [
        'service_id',
        'title',
        'quantity',
        'price'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
