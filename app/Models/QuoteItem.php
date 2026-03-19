<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'service_id',
        'concept',
        'description',
        'quantity',
        'unit_price',
        'billing_type'
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function costs()
    {
        return $this->hasMany(QuoteItemCost::class);
    }
}
