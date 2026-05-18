<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccountDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id', 'day',
        'followers', 'following', 'posts_count',
        'profile_views', 'page_impressions', 'page_reach',
        'raw',
    ];

    protected $casts = [
        'day' => 'date',
        'raw' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
