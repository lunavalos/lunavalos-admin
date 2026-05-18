<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;

    public const PROVIDER_FACEBOOK  = 'facebook';
    public const PROVIDER_INSTAGRAM = 'instagram';
    public const PROVIDER_LINKEDIN  = 'linkedin';
    public const PROVIDER_TIKTOK    = 'tiktok';
    public const PROVIDER_YOUTUBE   = 'youtube';

    public const PROVIDERS = [
        self::PROVIDER_FACEBOOK,
        self::PROVIDER_INSTAGRAM,
        self::PROVIDER_LINKEDIN,
        self::PROVIDER_TIKTOK,
        self::PROVIDER_YOUTUBE,
    ];

    protected $fillable = [
        'client_id', 'provider', 'provider_user_id', 'name', 'handle', 'avatar_url',
        'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'meta',
        'status', 'connected_by', 'last_synced_at',
    ];

    protected $casts = [
        'scopes'           => 'array',
        'meta'             => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at'   => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(SocialAccountDailyStat::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_FACEBOOK  => 'Facebook',
            self::PROVIDER_INSTAGRAM => 'Instagram',
            self::PROVIDER_LINKEDIN  => 'LinkedIn',
            self::PROVIDER_TIKTOK    => 'TikTok',
            self::PROVIDER_YOUTUBE   => 'YouTube',
            default                  => ucfirst($this->provider),
        };
    }
}
