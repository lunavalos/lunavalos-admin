<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;
use Illuminate\Support\Facades\Storage;

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

            if ($videoId) {
                $this->subirMiniatura($videoId, $token, $version, $target);
            }

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

    /**
     * Sube la portada como miniatura preferida del video.
     *
     * Va después de publicar y no como parámetro de `/videos`: la Graph API
     * solo acepta la imagen por `/{video-id}/thumbnails`, con el archivo en
     * multipart (no admite URL).
     *
     * Nunca tumba la publicación: el video ya salió, quedarse con la miniatura
     * automática es un detalle estético, no un fallo que haya que reintentar.
     */
    private function subirMiniatura(string $videoId, string $token, string $version, SocialPostTarget $target): void
    {
        $ruta = $this->rutaDePortada($target);
        if (!$ruta) {
            return;
        }

        try {
            $this->http()
                ->attach('source', Storage::disk('public')->get($ruta), basename($ruta))
                ->post("https://graph.facebook.com/{$version}/{$videoId}/thumbnails", [
                    'is_preferred' => 'true',
                    'access_token' => $token,
                ])
                ->throw();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
