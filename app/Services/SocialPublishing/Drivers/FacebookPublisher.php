<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;

class FacebookPublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account = $target->account;
        $post    = $target->post;
        $pageId  = $account->meta['page_id'] ?? $account->provider_user_id;
        // access_token ya es el page token (lo guarda así handleFacebook).
        $token   = $account->access_token;
        $version = config('services.facebook.graph_version', 'v19.0');

        $media = $this->mediaUrls($target);

        // Solo texto
        if (empty($media)) {
            $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/feed", [
                'message'      => $post->body,
                'access_token' => $token,
            ])->throw()->json();

            return [
                'id'  => $resp['id'] ?? null,
                'url' => isset($resp['id']) ? "https://facebook.com/{$resp['id']}" : null,
            ];
        }

        // Video: endpoint distinto y parámetros distintos. Mandarlo por /photos
        // devuelve 400 "Invalid parameter" (subcode 1366046), que no menciona
        // el video por ningún lado.
        if ($this->primerAdjuntoEsVideo($target)) {
            $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/videos", [
                'file_url'     => $media[0],
                'description'  => $post->body,
                'access_token' => $token,
            ])->throw()->json();

            $videoId = $resp['id'] ?? null;

            return [
                'id'  => $videoId,
                'url' => $videoId ? "https://facebook.com/{$videoId}" : null,
            ];
        }

        // Con foto (single image - flujo simple). Para múltiples imágenes habría que subir cada una
        // como unpublished y luego attached_media en /feed.
        $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/photos", [
            'url'          => $media[0],
            'caption'      => $post->body,
            'access_token' => $token,
        ])->throw()->json();

        $postId = $resp['post_id'] ?? $resp['id'] ?? null;

        return [
            'id'  => $postId,
            'url' => $postId ? "https://facebook.com/{$postId}" : null,
        ];
    }
}
