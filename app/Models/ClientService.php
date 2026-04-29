<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientService extends Model
{
    protected $fillable = [
        'client_id',
        'service_id',
        'service_name',
        'renewal_date',
        'renewal_amount',
        'initial_payment',
        'initial_cost',
        'status',
        'billing_type',
    ];

    protected $casts = [
        'renewal_date' => 'date',
        'renewal_amount' => 'decimal:2',
        'initial_payment' => 'decimal:2',
        'initial_cost' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
