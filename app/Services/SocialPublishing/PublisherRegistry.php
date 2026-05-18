<?php

namespace App\Services\SocialPublishing;

use App\Models\SocialAccount;
use App\Services\SocialPublishing\Drivers\FacebookPublisher;
use App\Services\SocialPublishing\Drivers\InstagramPublisher;
use App\Services\SocialPublishing\Drivers\LinkedInPublisher;
use App\Services\SocialPublishing\Drivers\TikTokPublisher;
use App\Services\SocialPublishing\Drivers\YouTubePublisher;

class PublisherRegistry
{
    /**
     * Devuelve el driver adecuado para el provider del SocialAccount.
     */
    public function for(SocialAccount $account): Publisher
    {
        return match ($account->provider) {
            SocialAccount::PROVIDER_FACEBOOK  => app(FacebookPublisher::class),
            SocialAccount::PROVIDER_INSTAGRAM => app(InstagramPublisher::class),
            SocialAccount::PROVIDER_LINKEDIN  => app(LinkedInPublisher::class),
            SocialAccount::PROVIDER_TIKTOK    => app(TikTokPublisher::class),
            SocialAccount::PROVIDER_YOUTUBE   => app(YouTubePublisher::class),
            default => throw new \InvalidArgumentException("Provider no soportado: {$account->provider}"),
        };
    }
}
