<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;

/**
 * TikTok Content Posting API — Direct Post / PULL_FROM_URL.
 * Doc: https://developers.tiktok.com/doc/content-posting-api-reference-direct-post-video
 * Requiere video accesible públicamente vía HTTPS.
 */
class TikTokPublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account = $target->account;
        $post    = $target->post;
        $token   = $account->access_token;

        $media = $this->mediaUrls($target);
        if (empty($media)) {
            throw new \RuntimeException('TikTok requiere un video.');
        }

        $resp = $this->http()->withToken($token)->post(
            'https://open.tiktokapis.com/v2/post/publish/video/init/',
            [
                'post_info' => [
                    'title'                  => mb_substr($post->title ?: ($post->body ?? ''), 0, 150),
                    'privacy_level'          => 'PUBLIC_TO_EVERYONE',
                    'disable_duet'           => false,
                    'disable_comment'        => false,
                    'disable_stitch'         => false,
                    'video_cover_timestamp_ms' => 1000,
                ],
                'source_info' => [
                    'source'          => 'PULL_FROM_URL',
                    'video_url'       => $media[0],
                ],
            ]
        )->throw()->json();

        $publishId = $resp['data']['publish_id'] ?? null;

        return [
            'id'  => $publishId,
            'url' => null, // TikTok devuelve URL solo cuando termina el procesado (polling con status endpoint).
        ];
    }
}
