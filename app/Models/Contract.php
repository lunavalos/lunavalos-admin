<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'contract_number',
        'quote_id',
        'client_id',
        'created_by_user_id',
        'token',
        'status',

        // Datos legales (alimentados en firma)
        'legal_name',
        'tax_id',
        'fiscal_address',
        'postal_code',
        'legal_representative',
        'csf_file_path',
        'signed_at',
        'signature_ip',
        'pdf_file_path',

        // Vigencia + montos snapshot
        'start_date',
        'end_date',
        'payment_plan_months',
        'subtotal',
        'discount_amount',
        'iva_amount',
        'retentions_total',
        'total_amount',
        'monthly_amount',
        'anticipo_amount',
        'notes',

        // Renovaciones
        'renewal_status',
        'renewal_last_notified_at',
        'renewal_notifications_sent',
        'renewed_contract_id',
    ];

    protected $casts = [
        'signed_at'           => 'datetime',
        'start_date'          => 'date',
        'end_date'            => 'date',
        'payment_plan_months' => 'integer',
        'subtotal'            => 'decimal:2',
        'discount_amount'     => 'decimal:2',
        'iva_amount'          => 'decimal:2',
        'retentions_total'    => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'monthly_amount'      => 'decimal:2',
        'anticipo_amount'     => 'decimal:2',
        'renewal_last_notified_at'  => 'datetime',
        'renewal_notifications_sent' => 'array',
    ];

    public const RENEWAL_STATUSES = ['none', 'pending', 'notified', 'renewed', 'declined', 'overdue'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function renewedContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_contract_id');
    }

    /**
     * Días restantes hasta end_date (negativo si ya venció).
     */
    public function daysUntilEnd(): ?int
    {
        return $this->end_date?->startOfDay()->diffInDays(now()->startOfDay(), false) * -1;
    }
}
