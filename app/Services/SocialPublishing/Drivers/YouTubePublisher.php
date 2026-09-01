<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;
use Illuminate\Support\Facades\Storage;

/**
 * YouTube Data API v3 — videos.insert (resumable upload simplificado).
 * Doc: https://developers.google.com/youtube/v3/docs/videos/insert
 * Aquí usamos uploadType=multipart, recomendado solo para videos < 256MB.
 */
class YouTubePublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account = $target->account;
        $post    = $target->post;
        $token   = $account->access_token;

        $media = $post->mediaPrincipal();
        if (empty($media)) {
            throw new \RuntimeException('YouTube requiere un archivo de video.');
        }

        $first = $media[0];
        $path  = is_array($first) ? ($first['path'] ?? null) : $first;
        if (!$path || !Storage::disk('public')->exists($path)) {
            throw new \RuntimeException('Archivo de video no encontrado en disco.');
        }

        $videoBytes = Storage::disk('public')->get($path);

        $metadata = json_encode([
            'snippet' => [
                'title'       => mb_substr($post->title ?: 'Video', 0, 100),
                'description' => $this->descripcion($post),
                'categoryId'  => $post->options['youtube_category_id'] ?? '22',
            ],
            'status' => [
                'privacyStatus' => $post->options['youtube_privacy'] ?? 'public',
            ],
        ]);

        // Multipart manual: boundary
        $boundary = '----LunavalosBoundary' . bin2hex(random_bytes(8));
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: video/*\r\n\r\n";
        $body .= $videoBytes . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $resp = $this->http()
            ->withToken($token)
            ->withHeaders(['Content-Type' => "multipart/related; boundary={$boundary}"])
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status')
            ->throw()
            ->json();

        $id = $resp['id'] ?? null;

        if ($id) {
            $this->subirMiniatura($id, $token, $target);
        }

        return [
            'id'  => $id,
            'url' => $id ? "https://www.youtube.com/watch?v={$id}" : null,
        ];
    }

    /**
     * La descripción, con `#Shorts` al final si se pidió publicar un short.
     *
     * La API no tiene ningún campo para marcar un video como Short: YouTube lo
     * decide por la duración y la relación de aspecto, y usa la etiqueta de la
     * descripción como señal. Sin ella, elegir "Short" en el compositor no
     * cambiaba absolutamente nada de lo que se subía.
     */
    private function descripcion($post): string
    {
        $texto = $post->body ?? '';

        if (($post->options['youtube_type'] ?? 'video') !== 'short') {
            return $texto;
        }

        if (stripos($texto, '#shorts') !== false) {
            return $texto;
        }

        return trim($texto . "\n\n#Shorts");
    }

    /**
     * Miniatura personalizada del video (thumbnails.set).
     *
     * Va en una llamada aparte: `videos.insert` no acepta la imagen. El canal
     * tiene que estar verificado; si no lo está Google responde 403
     * `forbidden` y ahí se queda la miniatura automática.
     *
     * No tumba la publicación: el video ya está subido y volver a intentarlo
     * lo duplicaría.
     */
    private function subirMiniatura(string $videoId, string $token, SocialPostTarget $target): void
    {
        $ruta = $this->rutaDePortada($target);
        if (!$ruta) {
            return;
        }

        try {
            $this->http()
                ->withToken($token)
                ->withBody(
                    Storage::disk('public')->get($ruta),
                    $target->post->portada()['mime'] ?? 'image/jpeg',
                )
                ->post("https://www.googleapis.com/upload/youtube/v3/thumbnails/set?videoId={$videoId}")
                ->throw();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function fetchAvatarUrl(\App\Models\SocialAccount $account): ?string
    {
        $resp = $this->http()
            ->withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'id'   => $account->provider_user_id,
            ])
            ->throw()
            ->json();

        $miniaturas = $resp['items'][0]['snippet']['thumbnails'] ?? [];

        return $miniaturas['medium']['url'] ?? $miniaturas['default']['url'] ?? null;
    }
}
