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

        $media = $post->media ?? [];
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
                'description' => $post->body ?? '',
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

        return [
            'id'  => $id,
            'url' => $id ? "https://www.youtube.com/watch?v={$id}" : null,
        ];
    }
}
