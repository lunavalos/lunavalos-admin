<?php

namespace App\Services\SocialPublishing\Drivers;

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

    protected function http()
    {
        return Http::acceptJson()->timeout(60);
    }
}
