<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCost extends Model
{
    protected $fillable = [
        'client_id',
        'concept',
        'amount',
        'billing_frequency'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
