<?php

namespace App\Actions\Social;

use App\Models\SocialAccount;
use App\Models\SocialAccountDailyStat;
use App\Services\SocialPublishing\PublisherRegistry;

class FetchAccountStats
{
    public function __construct(private PublisherRegistry $registry) {}

    public function __invoke(SocialAccount $account): ?SocialAccountDailyStat
    {
        if ($account->status !== 'active') {
            return null;
        }

        try {
            $data = $this->registry->for($account)->fetchAccountStats($account);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if (empty($data)) {
            return null;
        }

        return SocialAccountDailyStat::updateOrCreate(
            ['social_account_id' => $account->id, 'day' => now()->toDateString()],
            [
                'followers'        => (int) ($data['followers']        ?? 0),
                'following'        => (int) ($data['following']        ?? 0),
                'posts_count'      => (int) ($data['posts_count']      ?? 0),
                'profile_views'    => (int) ($data['profile_views']    ?? 0),
                'page_impressions' => (int) ($data['page_impressions'] ?? 0),
                'page_reach'       => (int) ($data['page_reach']       ?? 0),
                'raw'              => $data['raw'] ?? $data,
            ]
        );
    }
}
