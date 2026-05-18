<?php

namespace App\Console\Commands;

use App\Jobs\PublishSocialPostJob;
use App\Models\SocialPost;
use Illuminate\Console\Command;

class DispatchScheduledSocialPostsCommand extends Command
{
    protected $signature = 'social:dispatch-scheduled';
    protected $description = 'Encola posts sociales cuyo scheduled_at ya venció.';

    public function handle(): int
    {
        $posts = SocialPost::where('status', SocialPost::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        foreach ($posts as $post) {
            $this->info("Dispatch post #{$post->id} ({$post->title})");
            PublishSocialPostJob::dispatch($post->id);
        }

        $this->info("Total: {$posts->count()}");
        return self::SUCCESS;
    }
}
