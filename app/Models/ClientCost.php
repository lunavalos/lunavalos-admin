<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCost extends Model
{
    protected $fillable = [
        'client_id',
        'concept',
        'amount',
        'currency',
        'exchange_rate',
        'billing_frequency'
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'exchange_rate' => 'decimal:8',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
