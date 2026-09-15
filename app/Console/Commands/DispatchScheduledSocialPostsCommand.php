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

        $encolados = 0;

        foreach ($posts as $post) {
            // Marcamos el post ANTES de encolar y sólo si sigue en 'scheduled'.
            // El comando corre cada 5 minutos y el job puede tardar más que eso
            // (o la cola ir retrasada): sin este candado la siguiente corrida
            // volvía a encolar el mismo post y la red recibía la publicación
            // duplicada.
            $reclamado = SocialPost::whereKey($post->id)
                ->where('status', SocialPost::STATUS_SCHEDULED)
                ->update(['status' => SocialPost::STATUS_PUBLISHING]);

            if (!$reclamado) {
                continue;
            }

            $this->info("Dispatch post #{$post->id} ({$post->title})");
            PublishSocialPostJob::dispatch($post->id);
            $encolados++;
        }

        $this->info("Total: {$encolados}");

        return self::SUCCESS;
    }
}
