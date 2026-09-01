<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;
use Illuminate\Http\Client\RequestException;

/**
 * Instagram Graph API — requiere cuenta IG Business vinculada a una página de Facebook.
 * Flujo: 1) crear media container, 2) esperar a que Meta lo procese, 3) publicarlo.
 * Las imágenes/videos DEBEN estar accesibles públicamente por HTTPS.
 */
class InstagramPublisher extends AbstractPublisher
{
    /**
     * Tope de espera al procesado. Tiene que caber, junto con los reintentos
     * de publicación y la creación del contenedor, dentro del timeout del job
     * (300s): si el job se corta a media publicación el target se queda en
     * `publishing` para siempre.
     */
    private const ESPERA_MAXIMA_VIDEO = 200;

    /**
     * Una imagen se procesa en segundos, pero no en cero: Meta tiene que
     * descargarla de nuestro storage antes de darla por lista.
     */
    private const ESPERA_MAXIMA_IMAGEN = 60;

    private const INTERVALO_SONDEO_VIDEO  = 5;
    private const INTERVALO_SONDEO_IMAGEN = 2;

    /**
     * "Media ID is not available" (code 9007). Meta lo devuelve cuando el
     * contenedor todavía no está listo; reintentar tiene sentido.
     */
    private const SUBCODIGO_CONTENEDOR_NO_LISTO = 2207027;

    private const INTENTOS_DE_PUBLICACION = 3;

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

            // La carátula del reel. Sin esto Instagram toma el primer
            // fotograma, que casi siempre es lo peor que tiene el video, y el
            // reel se ve mal en la cuadrícula del perfil.
            //
            // `cover_url` gana sobre `thumb_offset`: mandar los dos hace que
            // Meta ignore uno sin avisar.
            if ($portada = $this->urlDePortada($target)) {
                $payload['cover_url'] = $portada;
            } elseif (($ms = $post->options['cover_timestamp_ms'] ?? null) !== null) {
                $payload['thumb_offset'] = (int) $ms;
            }
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

        // 2) El contenedor se procesa en segundo plano: Meta descarga el
        //    archivo de nuestro storage (y transcodifica, si es video).
        //    Publicar antes de que termine devuelve 400 code 9007 subcode
        //    2207027 "Media ID is not available".
        //
        //    También hay que sondear las imágenes. Se daban por listas al
        //    instante y en producción una foto falló justo así: el contenedor
        //    se creó, Meta pidió el PNG un segundo después y para entonces ya
        //    habíamos llamado a media_publish.
        $this->esperarProcesado($creationId, $token, $version, $esVideo);

        // 3) Publicar contenedor.
        $publishResp = $this->publicarContenedor($creationId, $igId, $token, $version, $esVideo);

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
     * Sondea el contenedor hasta que Meta lo dé por `FINISHED`.
     *
     * La primera consulta va sin esperar: en el caso normal —una imagen ya
     * descargada— el contenedor está listo y publicamos sin penalización.
     */
    private function esperarProcesado(string $creationId, string $token, string $version, bool $esVideo): void
    {
        $espera    = $esVideo ? self::ESPERA_MAXIMA_VIDEO : self::ESPERA_MAXIMA_IMAGEN;
        $intervalo = $esVideo ? self::INTERVALO_SONDEO_VIDEO : self::INTERVALO_SONDEO_IMAGEN;
        $limite    = time() + $espera;
        $medio     = $esVideo ? 'el video' : 'la imagen';

        while (true) {
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
                    "Instagram no pudo procesar {$medio}: " . ($estado['status'] ?? $codigo)
                );
            }

            if (time() >= $limite) {
                break;
            }

            $this->dormir($intervalo);
        }

        throw new \RuntimeException(
            "Instagram seguía procesando {$medio} después de {$espera} segundos. "
            . 'Prueba con un archivo más ligero.'
        );
    }

    /**
     * Publica el contenedor, reintentando mientras Meta diga que todavía no
     * está disponible.
     *
     * `status_code` puede decir FINISHED y aun así media_publish responder
     * 9007/2207027: la disponibilidad no se propaga de inmediato. Sin este
     * reintento el post se marcaba como fallido aunque no hubiera nada mal en
     * el contenido.
     */
    private function publicarContenedor(
        string $creationId,
        string $igId,
        string $token,
        string $version,
        bool $esVideo,
    ): array {
        $intervalo = $esVideo ? self::INTERVALO_SONDEO_VIDEO : self::INTERVALO_SONDEO_IMAGEN;

        for ($intento = 1; ; $intento++) {
            try {
                return $this->http()->asForm()->post(
                    "https://graph.facebook.com/{$version}/{$igId}/media_publish",
                    [
                        'creation_id'  => $creationId,
                        'access_token' => $token,
                    ]
                )->throw()->json();
            } catch (RequestException $e) {
                $reintentable = $this->subcodigoDeMeta($e) === self::SUBCODIGO_CONTENEDOR_NO_LISTO;

                if (!$reintentable || $intento >= self::INTENTOS_DE_PUBLICACION) {
                    throw new \RuntimeException($this->mensajeDeMeta($e), 0, $e);
                }

                $this->dormir($intervalo * $intento);
            }
        }
    }

    private function subcodigoDeMeta(RequestException $e): ?int
    {
        $subcodigo = $e->response?->json('error.error_subcode');

        return is_numeric($subcodigo) ? (int) $subcodigo : null;
    }

    /**
     * El mensaje de Meta, no el volcado del cuerpo HTTP.
     *
     * `RequestException` mete el JSON crudo y truncado en el mensaje, que es lo
     * que acababa en la columna `error_message` y en la interfaz: ilegible para
     * quien publica.
     */
    private function mensajeDeMeta(RequestException $e): string
    {
        $error   = $e->response?->json('error') ?? [];
        $mensaje = $error['error_user_msg'] ?? $error['message'] ?? null;

        if (!$mensaje) {
            return 'Instagram rechazó la publicación (HTTP ' . ($e->response?->status() ?? '?') . ').';
        }

        $codigos = array_filter([$error['code'] ?? null, $error['error_subcode'] ?? null]);

        return 'Instagram rechazó la publicación: ' . $mensaje
            . ($codigos ? ' (código ' . implode('/', $codigos) . ')' : '');
    }

    /** Aislado para poder anularlo en pruebas. */
    protected function dormir(int $segundos): void
    {
        sleep($segundos);
    }
}
