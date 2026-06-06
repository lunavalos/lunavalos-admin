<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientService extends Model
{
    protected $fillable = [
        'client_id',
        'contract_id',
        'service_id',
        'service_name',
        'renewal_date',
        'renewal_amount',
        'initial_payment',
        'initial_cost',
        'currency',
        'exchange_rate',
        'status',
        'billing_type',
        'renewal_email_sent_at',
    ];

    protected $casts = [
        'renewal_date'           => 'date',
        'renewal_amount'         => 'decimal:2',
        'initial_payment'        => 'decimal:2',
        'initial_cost'           => 'decimal:2',
        'exchange_rate'          => 'decimal:8',
        'renewal_email_sent_at'  => 'datetime',
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
