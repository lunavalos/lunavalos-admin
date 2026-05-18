<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    public const SOURCE_SUPPORT   = 'support';
    public const SOURCE_RECURRING = 'recurring';

    protected $fillable = [
        'title',
        'content',
        'priority',
        'status',
        'source_type',
        'creator_id',
        'assigned_id',
        'client_id',
        'client_service_id',
        'billing_cycle_id',
        'deliverable_credit_id',
        'sequence_number',
        'due_date',
        'status_updated_at',
        'work_started_at',
        'work_finished_at',
    ];

    protected $casts = [
        'due_date'          => 'datetime',
        'status_updated_at' => 'datetime',
        'work_started_at'   => 'datetime',
        'work_finished_at'  => 'datetime',
        'sequence_number'   => 'integer',
    ];

    protected $appends = ['code'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ticket) {
            if ($ticket->isDirty('status') || !$ticket->exists) {
                if (!$ticket->isDirty('status_updated_at')) {
                    $ticket->status_updated_at = now();
                }
            }
        });

        // Mantener actualizado el contador `consumed` del DeliverableCredit asociado.
        // Regla: un crédito se considera consumido cuando el ticket sale del Backlog
        // (status != 'Nuevos') y no está soft-deleted.
        $recompute = function ($ticket) {
            if ($ticket->source_type === self::SOURCE_RECURRING && $ticket->deliverable_credit_id) {
                self::recomputeCreditConsumed($ticket->deliverable_credit_id);
            }
        };
        static::saved($recompute);
        static::deleted($recompute);
        static::restored($recompute);
    }

    protected static function recomputeCreditConsumed(int $creditId): void
    {
        $consumed = static::query()
            ->where('deliverable_credit_id', $creditId)
            ->where('status', '!=', 'Nuevos')
            ->count();

        \App\Models\DeliverableCredit::where('id', $creditId)->update(['consumed' => $consumed]);
    }

    /* --------------------------------------------------------------------- */
    /* Scopes                                                                */
    /* --------------------------------------------------------------------- */

    public function scopeSupport($query)
    {
        return $query->where('source_type', self::SOURCE_SUPPORT);
    }

    public function scopeRecurring($query)
    {
        return $query->where('source_type', self::SOURCE_RECURRING);
    }

    /* --------------------------------------------------------------------- */
    /* Accessors                                                             */
    /* --------------------------------------------------------------------- */

    /**
     * Código visual tipo "R-03/08". Solo aplica a tickets recurrentes ligados
     * a un crédito; en otros casos devuelve null para no contaminar el Kanban
     * de soporte.
     */
    public function getCodeAttribute(): ?string
    {
        if ($this->source_type !== self::SOURCE_RECURRING) {
            return null;
        }

        $credit = $this->relationLoaded('deliverableCredit')
            ? $this->deliverableCredit
            : null;

        if (!$credit) {
            return null;
        }

        $cs = $credit->relationLoaded('contractService')
            ? $credit->contractService
            : null;

        $prefix = $cs?->prefix ?: 'T';

        if ($credit->is_unlimited) {
            return sprintf('%s-%02d/∞', $prefix, $this->sequence_number ?? 0);
        }

        $capacity = (int) $credit->total + (int) $credit->rolled_over;
        return sprintf('%s-%02d/%02d', $prefix, $this->sequence_number ?? 0, $capacity);
    }

    /* --------------------------------------------------------------------- */
    /* Relationships                                                         */
    /* --------------------------------------------------------------------- */

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assigned()
    {
        return $this->belongsTo(User::class, 'assigned_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientService()
    {
        return $this->belongsTo(ClientService::class, 'client_service_id');
    }

    public function billingCycle()
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function deliverableCredit()
    {
        return $this->belongsTo(DeliverableCredit::class);
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function canvasItems()
    {
        return $this->hasMany(TicketCanvasItem::class)->whereNull('parent_id')->orderBy('position');
    }
}
