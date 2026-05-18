<?php

namespace App\Jobs;

use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\SocialPublishing\PublisherRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishSocialPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // los reintentos los gestionamos a nivel target
    public int $timeout = 300;

    public function __construct(public int $socialPostId) {}

    public function handle(PublisherRegistry $registry): void
    {
        /** @var SocialPost $post */
        $post = SocialPost::with(['targets.account'])->find($this->socialPostId);
        if (!$post) return;
        if (in_array($post->status, [SocialPost::STATUS_PUBLISHED, SocialPost::STATUS_CANCELED], true)) {
            return;
        }

        $post->update(['status' => SocialPost::STATUS_PUBLISHING]);

        foreach ($post->targets as $target) {
            if ($target->status === SocialPostTarget::STATUS_PUBLISHED) continue;
            if (!$target->account || $target->account->status !== 'active') {
                $target->update([
                    'status'        => SocialPostTarget::STATUS_FAILED,
                    'error_message' => 'Cuenta social no disponible o revocada.',
                ]);
                continue;
            }

            try {
                $registry->for($target->account)->publish($target);
            } catch (\Throwable $e) {
                report($e);
                $target->update([
                    'status'        => SocialPostTarget::STATUS_FAILED,
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ]);
            }
        }

        $post->recomputeStatus();
    }
}
