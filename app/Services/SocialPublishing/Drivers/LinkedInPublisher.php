<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;

/**
 * LinkedIn UGC Posts API (v2)
 * Doc: https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api
 */
class LinkedInPublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account  = $target->account;
        $post     = $target->post;
        $token    = $account->access_token;
        $authorId = $account->meta['urn'] ?? "urn:li:person:{$account->provider_user_id}";

        $body = [
            'author'         => $authorId,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $post->body ?? ''],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ];

        $resp = $this->http()
            ->withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/ugcPosts', $body)
            ->throw();

        $id = $resp->json('id') ?? $resp->header('X-RestLi-Id');

        return [
            'id'  => $id,
            'url' => $id ? "https://www.linkedin.com/feed/update/{$id}" : null,
        ];
    }
}
