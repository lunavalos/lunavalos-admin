<?php

namespace App\Http\Controllers;

use App\Jobs\PublishSocialPostJob;
use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SocialController extends Controller
{
    public function index()
    {
        $clients = Client::query()
            ->whereHas('socialAccounts')
            ->with(['socialAccounts:id,client_id,provider,name,status,avatar_url'])
            ->withCount([
                'socialPosts as scheduled_count' => fn ($q) => $q->where('status', SocialPost::STATUS_SCHEDULED),
                'socialPosts as published_count' => fn ($q) => $q->where('status', SocialPost::STATUS_PUBLISHED),
            ])
            ->orderBy('business_name')
            ->get();

        return Inertia::render('Social/Dashboard', [
            'clients' => $clients,
            'allClients' => Client::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function show(Client $client, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $accounts = $client->socialAccounts()->orderBy('provider')->get();
        $posts    = $client->socialPosts()
            ->with(['targets.account:id,provider,name'])
            ->whereBetween(DB::raw('COALESCE(scheduled_at, created_at)'), [$start, $end])
            ->orderBy('scheduled_at')
            ->get();

        return Inertia::render('Social/ClientShow', [
            'client'   => $client->only(['id', 'business_name', 'contact_name']),
            'accounts' => $accounts,
            'posts'    => $posts,
            'month'    => $start->format('Y-m'),
            'availableProviders' => SocialAccount::PROVIDERS,
        ]);
    }

    public function createPost(Client $client)
    {
        $accounts = $client->socialAccounts()->where('status', 'active')->get();
        return Inertia::render('Social/PostComposer', [
            'client'   => $client->only(['id', 'business_name']),
            'accounts' => $accounts,
            'post'     => null,
        ]);
    }

    public function editPost(Client $client, SocialPost $post)
    {
        abort_if($post->client_id !== $client->id, 404);
        $post->load('targets');
        return Inertia::render('Social/PostComposer', [
            'client'   => $client->only(['id', 'business_name']),
            'accounts' => $client->socialAccounts()->where('status', 'active')->get(),
            'post'     => $post,
        ]);
    }

    public function storePost(Client $client, Request $request)
    {
        $data = $this->validatePost($request);

        $post = DB::transaction(function () use ($data, $client, $request) {
            $media = $this->storeMedia($request);

            $post = SocialPost::create([
                'client_id'    => $client->id,
                'title'        => $data['title'] ?? null,
                'body'         => $data['body'] ?? null,
                'media'        => $media,
                'options'      => $data['options'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status'       => $data['action'] === 'publish_now'
                    ? SocialPost::STATUS_PUBLISHING
                    : ($data['scheduled_at'] ? SocialPost::STATUS_SCHEDULED : SocialPost::STATUS_DRAFT),
                'created_by'   => $request->user()->id,
            ]);

            foreach ($data['account_ids'] as $accountId) {
                $account = SocialAccount::where('client_id', $client->id)->findOrFail($accountId);
                SocialPostTarget::create([
                    'social_post_id'    => $post->id,
                    'social_account_id' => $account->id,
                    'provider'          => $account->provider,
                    'status'            => SocialPostTarget::STATUS_PENDING,
                ]);
            }

            return $post;
        });

        if ($data['action'] === 'publish_now') {
            PublishSocialPostJob::dispatch($post->id);
        }

        return redirect()->route('social.clients.show', $client->id)
            ->with('success', $data['action'] === 'publish_now' ? 'Post encolado para publicación.' : 'Post guardado.');
    }

    public function updatePost(Client $client, SocialPost $post, Request $request)
    {
        abort_if($post->client_id !== $client->id, 404);
        abort_if(in_array($post->status, [SocialPost::STATUS_PUBLISHED, SocialPost::STATUS_PUBLISHING], true),
            422, 'No se puede editar un post ya publicado o en publicación.');

        $data = $this->validatePost($request);

        DB::transaction(function () use ($data, $post, $request) {
            $media = $this->storeMedia($request) ?: $post->media;

            $post->update([
                'title'        => $data['title'] ?? null,
                'body'         => $data['body'] ?? null,
                'media'        => $media,
                'options'      => $data['options'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status'       => $data['scheduled_at'] ? SocialPost::STATUS_SCHEDULED : SocialPost::STATUS_DRAFT,
            ]);

            $post->targets()->delete();
            foreach ($data['account_ids'] as $accountId) {
                $account = SocialAccount::where('client_id', $post->client_id)->findOrFail($accountId);
                SocialPostTarget::create([
                    'social_post_id'    => $post->id,
                    'social_account_id' => $account->id,
                    'provider'          => $account->provider,
                    'status'            => SocialPostTarget::STATUS_PENDING,
                ]);
            }
        });

        if ($data['action'] === 'publish_now') {
            $post->update(['status' => SocialPost::STATUS_PUBLISHING]);
            PublishSocialPostJob::dispatch($post->id);
        }

        return redirect()->route('social.clients.show', $client->id)->with('success', 'Post actualizado.');
    }

    public function destroyPost(Client $client, SocialPost $post)
    {
        abort_if($post->client_id !== $client->id, 404);
        abort_if($post->status === SocialPost::STATUS_PUBLISHED, 422, 'No se puede eliminar un post publicado.');
        $post->delete();
        return back()->with('success', 'Post eliminado.');
    }

    public function publishNow(Client $client, SocialPost $post)
    {
        abort_if($post->client_id !== $client->id, 404);
        $post->update(['status' => SocialPost::STATUS_PUBLISHING]);
        PublishSocialPostJob::dispatch($post->id);
        return back()->with('success', 'Post encolado para publicación inmediata.');
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title'         => 'nullable|string|max:255',
            'body'          => 'nullable|string|max:10000',
            'scheduled_at'  => 'nullable|date',
            'account_ids'   => 'required|array|min:1',
            'account_ids.*' => 'integer|exists:social_accounts,id',
            'action'        => 'required|in:save_draft,schedule,publish_now',
            'options'       => 'nullable|array',
            'media.*'       => 'nullable|file|max:204800', // 200 MB
        ]);
    }

    private function storeMedia(Request $request): array
    {
        $stored = [];
        foreach ((array) $request->file('media', []) as $file) {
            if (!$file) continue;
            $path = $file->store('social', 'public');
            $stored[] = ['path' => $path, 'mime' => $file->getMimeType(), 'name' => $file->getClientOriginalName()];
        }
        return $stored;
    }
}
