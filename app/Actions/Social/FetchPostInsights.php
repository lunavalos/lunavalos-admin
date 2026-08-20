<?php

namespace App\Actions\Social;

use App\Models\SocialPostMetric;
use App\Models\SocialPostTarget;
use App\Services\SocialPublishing\PublisherRegistry;

class FetchPostInsights
{
    public function __construct(private PublisherRegistry $registry) {}

    public function __invoke(SocialPostTarget $target): ?SocialPostMetric
    {
        if ($target->status !== SocialPostTarget::STATUS_PUBLISHED || !$target->platform_post_id) {
            return null;
        }

        // La cuenta pudo desconectarse después de publicar: el target se
        // conserva como historial, pero sin token no hay a quién preguntarle
        // las métricas.
        if (!$target->account) {
            return null;
        }

        try {
            $data = $this->registry->for($target->account)->fetchInsights($target);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if (empty($data)) {
            return null;
        }

        $likes    = (int) ($data['likes']    ?? 0);
        $comments = (int) ($data['comments'] ?? 0);
        $shares   = (int) ($data['shares']   ?? 0);
        $saves    = (int) ($data['saves']    ?? 0);
        $reach    = (int) ($data['reach']    ?? 0);
        $engagement = $reach > 0
            ? round(($likes + $comments + $shares + $saves) / $reach, 4)
            : (float) ($data['engagement_rate'] ?? 0);

        return SocialPostMetric::updateOrCreate(
            ['social_post_target_id' => $target->id],
            [
                'provider'        => $target->provider,
                'impressions'     => (int) ($data['impressions'] ?? 0),
                'reach'           => $reach,
                'likes'           => $likes,
                'comments'        => $comments,
                'shares'          => $shares,
                'saves'           => $saves,
                'clicks'          => (int) ($data['clicks']      ?? 0),
                'video_views'     => (int) ($data['video_views'] ?? 0),
                'engagement_rate' => $engagement,
                'raw'             => $data['raw'] ?? $data,
                'fetched_at'      => now(),
            ]
        );
    }
}
