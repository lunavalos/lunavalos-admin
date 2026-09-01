<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;
use Illuminate\Support\Facades\Storage;

/**
 * Facebook Pages. El tipo de publicación lo elige quien compone el post
 * (`facebook_type`) y cada uno usa un endpoint distinto: no basta con mirar el
 * adjunto, porque un mismo video sale como reel o como video normal según lo
 * que se haya pedido.
 */
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

        // Los posts guardados antes de que existiera el selector no traen
        // `facebook_type`: para ellos se deduce del adjunto, que es justo lo
        // que hacía este driver.
        $tipo = $post->options['facebook_type'] ?? match (true) {
            empty($media)                        => 'post',
            $this->primerAdjuntoEsVideo($target) => 'video',
            default                              => 'photo',
        };

        if ($tipo !== 'post' && empty($media)) {
            throw new \RuntimeException("Facebook: el formato «{$tipo}» requiere un archivo adjunto.");
        }

        return match ($tipo) {
            'reel'  => $this->publicarReel($pageId, $token, $version, $media[0], $post->body, $target),
            'video' => $this->publicarVideo($pageId, $token, $version, $media[0], $post->body, $target),
            'photo' => $this->publicarFoto($pageId, $token, $version, $media[0], $post->body),
            default => $this->publicarTexto($pageId, $token, $version, $post->body),
        };
    }

    private function publicarTexto(string $pageId, string $token, string $version, ?string $mensaje): array
    {
        $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/feed", [
            'message'      => $mensaje,
            'access_token' => $token,
        ])->throw()->json();

        return $this->resultado($resp['id'] ?? null);
    }

    private function publicarFoto(string $pageId, string $token, string $version, string $url, ?string $texto): array
    {
        // Para varias imágenes habría que subir cada una como unpublished y
        // luego referenciarlas con attached_media en /feed.
        $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/photos", [
            'url'          => $url,
            'caption'      => $texto,
            'access_token' => $token,
        ])->throw()->json();

        return $this->resultado($resp['post_id'] ?? $resp['id'] ?? null);
    }

    /**
     * Video normal. Endpoint y parámetros distintos de los de foto: mandar un
     * .mp4 por /photos devuelve 400 "Invalid parameter" (subcode 1366046), que
     * no menciona el video por ningún lado.
     */
    private function publicarVideo(
        string $pageId,
        string $token,
        string $version,
        string $url,
        ?string $texto,
        SocialPostTarget $target,
    ): array {
        $resp = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/videos", [
            'file_url'     => $url,
            'description'  => $texto,
            'access_token' => $token,
        ])->throw()->json();

        $videoId = $resp['id'] ?? null;

        if ($videoId) {
            $this->subirMiniatura($videoId, $token, $version, $target);
        }

        return $this->resultado($videoId);
    }

    /**
     * Reels. No salen por /videos: tienen su propia API en tres fases —reservar
     * el video, subir el archivo y publicar—. Un reel mandado a /videos queda
     * como video normal del feed, que es exactamente lo que pasaba al elegir
     * "Reel" en el compositor.
     *
     * La subida usa el modo alojado (cabecera `file_url`): Meta descarga el
     * archivo de nuestro storage en vez de que le mandemos los bytes.
     */
    private function publicarReel(
        string $pageId,
        string $token,
        string $version,
        string $url,
        ?string $texto,
        SocialPostTarget $target,
    ): array {
        $inicio = $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/video_reels", [
            'upload_phase' => 'start',
            'access_token' => $token,
        ])->throw()->json();

        $videoId = $inicio['video_id'] ?? null;
        if (!$videoId) {
            throw new \RuntimeException('Facebook no devolvió el id del reel al iniciar la subida.');
        }

        $subida = $inicio['upload_url'] ?? "https://rupload.facebook.com/video-upload/{$version}/{$videoId}";

        $this->http()
            ->withHeaders([
                'Authorization' => "OAuth {$token}",
                'file_url'      => $url,
            ])
            ->post($subida)
            ->throw();

        $this->http()->asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/video_reels", [
            'upload_phase' => 'finish',
            'video_id'     => $videoId,
            'video_state'  => 'PUBLISHED',
            'description'  => $texto,
            'access_token' => $token,
        ])->throw();

        $this->subirMiniatura($videoId, $token, $version, $target);

        return [
            'id'  => $videoId,
            'url' => "https://facebook.com/reel/{$videoId}",
        ];
    }

    private function resultado(?string $id): array
    {
        return [
            'id'  => $id,
            'url' => $id ? "https://facebook.com/{$id}" : null,
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

    /**
     * Foto de la página. `redirect=false` hace que Graph devuelva la URL en
     * JSON en vez de responder con la imagen.
     */
    public function fetchAvatarUrl(\App\Models\SocialAccount $account): ?string
    {
        $pageId  = $account->meta['page_id'] ?? $account->provider_user_id;
        $version = config('services.facebook.graph_version', 'v19.0');

        $resp = $this->http()->get("https://graph.facebook.com/{$version}/{$pageId}/picture", [
            'redirect'     => 'false',
            'type'         => 'large',
            'access_token' => $account->access_token,
        ])->throw()->json();

        return $resp['data']['url'] ?? null;
    }
}
