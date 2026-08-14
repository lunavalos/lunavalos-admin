<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\SocialPublishing\Publisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class AbstractPublisher implements Publisher
{
    public function publish(SocialPostTarget $target): SocialPostTarget
    {
        $target->status          = SocialPostTarget::STATUS_PUBLISHING;
        $target->attempts        = $target->attempts + 1;
        $target->last_attempt_at = now();
        $target->save();

        try {
            $result = $this->doPublish($target);

            $target->fill([
                'platform_post_id' => $result['id']  ?? null,
                'platform_url'     => $result['url'] ?? null,
                'status'           => SocialPostTarget::STATUS_PUBLISHED,
                'published_at'     => now(),
                'error_message'    => null,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $target->fill([
                'status'        => SocialPostTarget::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ])->save();
        }

        return $target->fresh();
    }

    /**
     * Implementación específica del driver.
     * Debe devolver ['id' => 'xxx', 'url' => 'https://...']
     */
    abstract protected function doPublish(SocialPostTarget $target): array;

    /**
     * Devuelve URLs absolutas de los media adjuntos al post.
     */
    protected function mediaUrls(SocialPostTarget $target): array
    {
        $media = $target->post->media ?? [];
        return collect($media)->map(function ($m) {
            $path = is_array($m) ? ($m['path'] ?? null) : $m;
            if (!$path) return null;
            return Storage::disk('public')->url($path);
        })->filter()->values()->all();
    }

    /**
     * Si el primer adjunto es video. Solo se publica el primero.
     *
     * Importa porque Meta usa endpoints y parámetros distintos para foto y
     * video: mandar un .mp4 por la vía de imagen falla con errores que no
     * mencionan el video (400 subcode 1366046 en Pages, 500 "reduce the amount
     * of data" en Instagram).
     */
    protected function primerAdjuntoEsVideo(SocialPostTarget $target): bool
    {
        $primero = ($target->post->media ?? [])[0] ?? null;
        if ($primero === null) {
            return false;
        }

        if (is_array($primero) && str_starts_with((string) ($primero['mime'] ?? ''), 'video/')) {
            return true;
        }

        $path = is_array($primero) ? (string) ($primero['path'] ?? '') : (string) $primero;

        return (bool) preg_match('/\.(mp4|mov|m4v|webm)$/i', $path);
    }

    protected function http()
    {
        return Http::acceptJson()->timeout(60);
    }

    /**
     * Por defecto los drivers no implementan analytics todavía.
     * Cada driver puede sobrescribir estos métodos cuando se integre el endpoint real.
     */
    public function fetchInsights(SocialPostTarget $target): array
    {
        return [];
    }

    public function fetchAccountStats(SocialAccount $account): array
    {
        return [];
    }
}
