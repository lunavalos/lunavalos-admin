<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPostMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_post_target_id', 'provider',
        'impressions', 'reach', 'likes', 'comments', 'shares', 'saves',
        'clicks', 'video_views', 'engagement_rate', 'raw', 'fetched_at',
    ];

    protected $casts = [
        'raw'             => 'array',
        'fetched_at'      => 'datetime',
        'engagement_rate' => 'float',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(SocialPostTarget::class, 'social_post_target_id');
    }

    /** Total de interacciones para cálculos rápidos. */
    public function getInteractionsAttribute(): int
    {
        return (int) ($this->likes + $this->comments + $this->shares + $this->saves);
    }
}
