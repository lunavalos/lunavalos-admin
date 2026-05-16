<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'contract_id',
        'client_payment_id',
        'issued_by_user_id',
        'facturama_id',
        'uuid',
        'series',
        'folio',
        'cfdi_type',
        'payment_method',
        'payment_form',
        'cfdi_use',
        'currency',
        'subtotal',
        'discount',
        'taxes',
        'retentions',
        'total',
        'status',
        'cancellation_status',
        'issued_at',
        'canceled_at',
        'xml_path',
        'pdf_path',
        'request_snapshot',
        'response_snapshot',
        'error_message',
    ];

    protected $casts = [
        'subtotal'           => 'decimal:2',
        'discount'           => 'decimal:2',
        'taxes'              => 'decimal:2',
        'retentions'         => 'decimal:2',
        'total'              => 'decimal:2',
        'issued_at'          => 'datetime',
        'canceled_at'        => 'datetime',
        'request_snapshot'   => 'array',
        'response_snapshot'  => 'array',
    ];

    public const STATUSES = ['issued', 'canceled', 'error'];

    public function client(): BelongsTo        { return $this->belongsTo(Client::class); }
    public function contract(): BelongsTo      { return $this->belongsTo(Contract::class); }
    public function payment(): BelongsTo       { return $this->belongsTo(ClientPayment::class, 'client_payment_id'); }
    public function issuedBy(): BelongsTo      { return $this->belongsTo(User::class, 'issued_by_user_id'); }
}
