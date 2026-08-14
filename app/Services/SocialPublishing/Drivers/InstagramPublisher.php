<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;

/**
 * Instagram Graph API — requiere cuenta IG Business vinculada a una página de Facebook.
 * Flujo: 1) crear media container, 2) esperar si es video, 3) publicar container.
 * Las imágenes/videos DEBEN estar accesibles públicamente por HTTPS.
 */
class InstagramPublisher extends AbstractPublisher
{
    /** Tope de espera al procesado del video, por debajo del timeout del job (300s). */
    private const ESPERA_MAXIMA_SEGUNDOS = 240;
    private const INTERVALO_SONDEO_SEGUNDOS = 5;

    protected function doPublish(SocialPostTarget $target): array
    {
        $account = $target->account;
        $post    = $target->post;
        $igId    = $account->meta['ig_business_id'] ?? null;
        // access_token ya es el page token (lo guarda así handleInstagram).
        $token   = $account->access_token;
        $version = config('services.facebook.graph_version', 'v19.0');

        if (!$igId) {
            throw new \RuntimeException('IG Business account no configurada. Reconecta la cuenta.');
        }

        $media = $this->mediaUrls($target);
        if (empty($media)) {
            throw new \RuntimeException('Instagram requiere al menos una imagen o video.');
        }

        $esVideo = $this->primerAdjuntoEsVideo($target);

        // 1) Crear contenedor.
        $payload = [
            'caption'      => $post->body,
            'access_token' => $token,
        ];

        if ($esVideo) {
            // REELS es el único formato de video que acepta la API de
            // publicación de contenido; `video_url`, no `image_url`.
            $payload['media_type'] = 'REELS';
            $payload['video_url']  = $media[0];
        } else {
            $payload['image_url'] = $media[0];
        }

        $containerResp = $this->http()->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igId}/media",
            $payload
        )->throw()->json();

        $creationId = $containerResp['id'] ?? null;
        if (!$creationId) {
            throw new \RuntimeException('No se pudo crear el media container de Instagram.');
        }

        // 2) Los contenedores de video se procesan en segundo plano: Meta
        //    descarga el archivo y lo transcodifica. Publicar antes de que
        //    termine falla. Las imágenes quedan listas de inmediato.
        if ($esVideo) {
            $this->esperarProcesado($creationId, $token, $version);
        }

        // 3) Publicar contenedor.
        $publishResp = $this->http()->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igId}/media_publish",
            [
                'creation_id'  => $creationId,
                'access_token' => $token,
            ]
        )->throw()->json();

        $mediaId = $publishResp['id'] ?? null;

        return [
            'id'  => $mediaId,
            'url' => $mediaId ? $this->permalink($mediaId, $token, $version) : null,
        ];
    }

    /**
     * El permalink hay que pedirlo: el id que devuelve media_publish es
     * numérico y las URLs públicas de Instagram usan un shortcode distinto,
     * así que armar "instagram.com/p/{id}" da 404 aunque el post exista.
     *
     * Si la consulta falla no se tumba la publicación —ya salió—, solo se
     * queda sin enlace.
     */
    private function permalink(string $mediaId, string $token, string $version): ?string
    {
        try {
            $resp = $this->http()->get("https://graph.facebook.com/{$version}/{$mediaId}", [
                'fields'       => 'permalink',
                'access_token' => $token,
            ])->throw()->json();

            return $resp['permalink'] ?? null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Sondea el contenedor hasta que Meta termine de procesar el video.
     */
    private function esperarProcesado(string $creationId, string $token, string $version): void
    {
        $limite = time() + self::ESPERA_MAXIMA_SEGUNDOS;

        while (time() < $limite) {
            $this->dormir(self::INTERVALO_SONDEO_SEGUNDOS);

            $estado = $this->http()->get("https://graph.facebook.com/{$version}/{$creationId}", [
                'fields'       => 'status_code,status',
                'access_token' => $token,
            ])->throw()->json();

            $codigo = $estado['status_code'] ?? '';

            if ($codigo === 'FINISHED') {
                return;
            }

            if (in_array($codigo, ['ERROR', 'EXPIRED'], true)) {
                throw new \RuntimeException(
                    'Instagram no pudo procesar el video: ' . ($estado['status'] ?? $codigo)
                );
            }
        }

        throw new \RuntimeException(
            'Instagram seguía procesando el video después de '
            . self::ESPERA_MAXIMA_SEGUNDOS . ' segundos. Prueba con un archivo más ligero.'
        );
    }

    /** Aislado para poder anularlo en pruebas. */
    protected function dormir(int $segundos): void
    {
        sleep($segundos);
    }
}
