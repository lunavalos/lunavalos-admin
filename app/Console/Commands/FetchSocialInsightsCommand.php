<?php

namespace App\Console\Commands;

use App\Actions\Social\FetchPostInsights;
use App\Models\SocialPostTarget;
use Illuminate\Console\Command;

class FetchSocialInsightsCommand extends Command
{
    protected $signature = 'social:fetch-insights {--days=90 : Solo posts publicados en los últimos N días} {--client= : Limitar a un cliente}';
    protected $description = 'Sincroniza métricas (impressions, likes, etc.) de los posts publicados.';

    public function handle(FetchPostInsights $fetch): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $clientId = $this->option('client');

        $query = SocialPostTarget::query()
            ->where('status', SocialPostTarget::STATUS_PUBLISHED)
            ->whereNotNull('platform_post_id')
            ->where('published_at', '>=', $since)
            ->with('account');

        if ($clientId) {
            $query->whereHas('post', fn ($q) => $q->where('client_id', $clientId));
        }

        $count = 0;
        $query->chunkById(50, function ($targets) use ($fetch, &$count) {
            foreach ($targets as $target) {
                if ($fetch($target)) {
                    $count++;
                }
            }
        });

        $this->info("Métricas sincronizadas: {$count}.");
        return self::SUCCESS;
    }
}
