<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialPostTarget extends Model
{
    use HasFactory;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED  = 'published';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_SKIPPED    = 'skipped';

    protected $fillable = [
        'social_post_id', 'social_account_id', 'provider', 'status',
        'platform_post_id', 'platform_url', 'published_at', 'error_message',
        'attempts', 'last_attempt_at',
    ];

    protected $casts = [
        'published_at'    => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function metric(): HasOne
    {
        return $this->hasOne(SocialPostMetric::class, 'social_post_target_id');
    }
}
