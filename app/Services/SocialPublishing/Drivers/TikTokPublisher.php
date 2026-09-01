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

        $opciones = $post->options ?? [];

        $origen = [
            'source'    => 'PULL_FROM_URL',
            'video_url' => $media[0],
        ];

        // "Enviar a borradores" es otro endpoint, no una variante del mismo:
        // el inbox deja el video en la app de TikTok para que el creador lo
        // termine, y por eso no acepta `post_info`. Publicaba directo al perfil
        // aunque se eligiera borrador.
        if (($opciones['tiktok_type'] ?? 'video') === 'draft') {
            $resp = $this->http()->withToken($token)->post(
                'https://open.tiktokapis.com/v2/post/publish/inbox/video/init/',
                ['source_info' => $origen],
            )->throw()->json();

            return [
                'id'  => $resp['data']['publish_id'] ?? null,
                'url' => null, // Todavía no hay publicación: está en borradores.
            ];
        }

        $resp = $this->http()->withToken($token)->post(
            'https://open.tiktokapis.com/v2/post/publish/video/init/',
            [
                'post_info' => [
                    'title'           => mb_substr($post->title ?: ($post->body ?? ''), 0, 150),
                    'privacy_level'   => $opciones['tiktok_privacy'] ?? 'PUBLIC_TO_EVERYONE',
                    'disable_duet'    => (bool) ($opciones['tiktok_disable_duet'] ?? false),
                    'disable_comment' => (bool) ($opciones['tiktok_disable_comment'] ?? false),
                    'disable_stitch'  => (bool) ($opciones['tiktok_disable_stitch'] ?? false),
                    // TikTok no acepta una imagen de portada: solo el segundo
                    // del video que quieres de carátula. Lo elige quien publica
                    // desde el compositor; 1s es el valor por omisión porque el
                    // fotograma 0 suele ser negro.
                    'video_cover_timestamp_ms' => (int) ($opciones['cover_timestamp_ms'] ?? 1000),
                ],
                'source_info' => $origen,
            ]
        )->throw()->json();

        $publishId = $resp['data']['publish_id'] ?? null;

        return [
            'id'  => $publishId,
            'url' => null, // TikTok devuelve URL solo cuando termina el procesado (polling con status endpoint).
        ];
    }

    public function fetchAvatarUrl(\App\Models\SocialAccount $account): ?string
    {
        $resp = $this->http()
            ->withToken($account->access_token)
            ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'avatar_url'])
            ->throw()
            ->json();

        return $resp['data']['user']['avatar_url'] ?? null;
    }
}
