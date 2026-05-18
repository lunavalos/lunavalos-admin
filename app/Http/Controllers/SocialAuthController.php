<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Mapeo provider lógico -> driver de Socialite + scopes.
     */
    private array $config = [
        'facebook' => [
            'driver' => 'facebook',
            'scopes' => ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'pages_manage_engagement', 'public_profile'],
        ],
        'instagram' => [
            'driver' => 'facebook',
            'scopes' => ['pages_show_list', 'instagram_basic', 'instagram_content_publish', 'pages_read_engagement', 'business_management'],
        ],
        'linkedin' => [
            'driver' => 'linkedin-openid',
            'scopes' => ['openid', 'profile', 'email', 'w_member_social'],
        ],
        'tiktok' => [
            'driver' => 'tiktok',
            'scopes' => ['user.info.basic', 'video.publish', 'video.upload'],
        ],
        'youtube' => [
            'driver' => 'google',
            'scopes' => ['openid', 'email', 'profile', 'https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube'],
        ],
    ];

    public function redirect(Request $request, string $provider, Client $client)
    {
        abort_unless(isset($this->config[$provider]), 404);
        $cfg = $this->config[$provider];

        // Guardamos client_id en sesión para recuperarlo en el callback
        $request->session()->put('social_oauth.client_id', $client->id);
        $request->session()->put('social_oauth.provider', $provider);

        return Socialite::driver($cfg['driver'])
            ->scopes($cfg['scopes'])
            ->with(['prompt' => 'consent', 'access_type' => 'offline']) // Google: refresh_token
            ->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(isset($this->config[$provider]), 404);
        $cfg = $this->config[$provider];

        $clientId = $request->session()->pull('social_oauth.client_id');
        abort_unless($clientId, 400, 'Sesión de OAuth expirada.');
        $client = Client::findOrFail($clientId);

        try {
            $socialUser = Socialite::driver($cfg['driver'])->user();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('social.clients.show', $client->id)
                ->withErrors(['oauth' => 'No se pudo completar la autorización: ' . $e->getMessage()]);
        }

        $meta = $this->buildMeta($provider, $socialUser);

        $account = SocialAccount::updateOrCreate(
            [
                'provider'         => $provider,
                'provider_user_id' => (string) $socialUser->getId(),
            ],
            [
                'client_id'        => $client->id,
                'name'             => $socialUser->getName() ?? $socialUser->getNickname() ?? $provider,
                'handle'           => $socialUser->getNickname(),
                'avatar_url'       => $socialUser->getAvatar(),
                'access_token'     => $socialUser->token,
                'refresh_token'    => $socialUser->refreshToken ?? null,
                'token_expires_at' => isset($socialUser->expiresIn)
                    ? now()->addSeconds($socialUser->expiresIn) : null,
                'scopes'           => $cfg['scopes'],
                'meta'             => $meta,
                'status'           => 'active',
                'connected_by'     => $request->user()?->id,
                'last_synced_at'   => now(),
            ]
        );

        return redirect()->route('social.clients.show', $client->id)
            ->with('success', "Cuenta de {$account->providerLabel()} conectada.");
    }

    public function disconnect(SocialAccount $account)
    {
        $clientId = $account->client_id;
        $account->update(['status' => 'revoked', 'access_token' => '', 'refresh_token' => null]);
        $account->delete();

        return redirect()->route('social.clients.show', $clientId)
            ->with('success', 'Cuenta desconectada.');
    }

    /**
     * Información extra por provider (page_id de FB, ig_business_id, urn de LinkedIn, etc.).
     */
    private function buildMeta(string $provider, $socialUser): array
    {
        $meta = ['raw' => $socialUser->getRaw()];

        if ($provider === 'facebook' || $provider === 'instagram') {
            // Recupera la primera Page del usuario y su page access token.
            try {
                $version = config('services.facebook.graph_version', 'v19.0');
                $pages = Http::get("https://graph.facebook.com/{$version}/me/accounts", [
                    'access_token' => $socialUser->token,
                    'fields'       => 'id,name,access_token,instagram_business_account',
                ])->throw()->json('data', []);

                if (!empty($pages)) {
                    $page = $pages[0];
                    $meta['page_id']     = $page['id'] ?? null;
                    $meta['page_name']   = $page['name'] ?? null;
                    $meta['page_token']  = $page['access_token'] ?? null;
                    if (!empty($page['instagram_business_account']['id'])) {
                        $meta['ig_business_id'] = $page['instagram_business_account']['id'];
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($provider === 'linkedin') {
            $sub = $socialUser->getRaw()['sub'] ?? $socialUser->getId();
            $meta['urn'] = "urn:li:person:{$sub}";
        }

        if ($provider === 'youtube') {
            // canal por defecto del usuario autenticado
            try {
                $channels = Http::withToken($socialUser->token)->get(
                    'https://www.googleapis.com/youtube/v3/channels',
                    ['part' => 'snippet,contentDetails', 'mine' => 'true']
                )->throw()->json('items', []);
                if (!empty($channels)) {
                    $meta['channel_id']    = $channels[0]['id'] ?? null;
                    $meta['channel_title'] = $channels[0]['snippet']['title'] ?? null;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $meta;
    }
}
