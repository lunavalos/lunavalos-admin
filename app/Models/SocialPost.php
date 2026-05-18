<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPost extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_SCHEDULED  = 'scheduled';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED  = 'published';
    public const STATUS_PARTIAL    = 'partial';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELED   = 'canceled';

    protected $fillable = [
        'client_id', 'ticket_id', 'title', 'body', 'media', 'options',
        'scheduled_at', 'published_at', 'status', 'error_message',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'media'        => 'array',
        'options'      => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    public function recomputeStatus(): void
    {
        $this->loadMissing('targets');
        $statuses = $this->targets->pluck('status');
        if ($statuses->isEmpty()) return;

        if ($statuses->every(fn ($s) => $s === SocialPostTarget::STATUS_PUBLISHED)) {
            $this->status       = self::STATUS_PUBLISHED;
            $this->published_at = $this->published_at ?? now();
        } elseif ($statuses->contains(SocialPostTarget::STATUS_PUBLISHED)) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($statuses->every(fn ($s) => $s === SocialPostTarget::STATUS_FAILED)) {
            $this->status = self::STATUS_FAILED;
        } elseif ($statuses->contains(SocialPostTarget::STATUS_PUBLISHING)) {
            $this->status = self::STATUS_PUBLISHING;
        }
        $this->save();
    }
}
