<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * OAuth flow para conectar/desconectar cuentas sociales por cliente.
 *
 * Diseño:
 *   - redirect(): construye el driver de Socialite con los scopes y parámetros
 *     específicos del provider (los scopes NO se leen automáticamente desde
 *     config/services.php; deben pasarse vía ->scopes([])).
 *   - callback(): delega a un handler por provider que decide cuántas filas
 *     en social_accounts deben crearse:
 *       facebook  -> 1 fila por cada Page administrada (NO se guarda el user personal).
 *       instagram -> 1 fila por cada IG Business vinculada a una Page.
 *       linkedin  -> 1 fila para el perfil personal + 1 por cada Organization con rol ADMIN.
 *       tiktok    -> 1 fila para el usuario autenticado.
 *       youtube   -> 1 fila por canal de YouTube administrado.
 *
 * Notas importantes:
 *   - El índice unique(provider, provider_user_id) de social_accounts obliga a
 *     que provider_user_id sea el id del recurso publicable (page_id, ig_id,
 *     org id, channel id), NO el id del usuario que autorizó.
 *   - Para Facebook se intercambia el token corto por uno largo (60 días); los
 *     page_access_token derivados de un long-lived user token no expiran.
 */
class SocialAuthController extends Controller implements HasMiddleware
{
    /**
     * Conectar y desconectar cuentas es gestión del módulo de redes, no lectura.
     *
     * `callback` va con el mismo permiso que `redirect`: es la vuelta del mismo
     * flujo y la inicia el mismo usuario, así que quien pudo arrancarlo puede
     * cerrarlo.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:Gestionar Social'),
        ];
    }

    /** Provider lógico -> driver real de Socialite. */
    private const DRIVERS = [
        'facebook'  => 'facebook',
        'instagram' => 'facebook',          // IG usa el OAuth de Facebook
        'linkedin'  => 'linkedin-openid',
        'tiktok'    => 'tiktok',
        'youtube'   => 'google',
    ];

    public function redirect(Request $request, string $provider, Client $client)
    {
        abort_unless(isset(self::DRIVERS[$provider]), 404);
        $this->autorizarCliente($request, $client->id);

        // Guardamos client_id en sesión para recuperarlo en el callback.
        $request->session()->put('social_oauth.client_id', $client->id);
        $request->session()->put('social_oauth.provider', $provider);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver(self::DRIVERS[$provider]);
        $driver->redirectUrl($this->redirectUrlFor($provider));

        // Parámetros extra por provider. NO se mandan a todos por defecto
        // porque Facebook/LinkedIn rechazan/ignoran `access_type=offline` y
        // `prompt=consent` puede romper algunos flujos.
        $extra = $this->extraParamsFor($provider);

        $configId = $this->loginConfigIdFor($provider);

        if ($configId !== null) {
            // Facebook Login for Business: los permisos y los activos vienen de
            // la configuración del panel. Mandar `scope` además del config_id
            // no aporta y Meta lo ignora, así que se limpia.
            $driver->setScopes([]);
            $extra['config_id'] = $configId;
        } else {
            // setScopes() REEMPLAZA; scopes() fusiona con los del driver
            // (`FacebookProvider::$scopes = ['email']`) y con los de
            // config/services.php. Con scopes() el diálogo recibía `email`
            // aunque no esté aprobado, y Meta respondía "Invalid Scopes: email".
            $driver->setScopes($this->scopesFor($provider));
        }
        if (!empty($extra)) {
            $driver->with($extra);
        }

        Log::info('[oauth-debug] redirect:init', [
            'provider' => $provider,
            'driver' => self::DRIVERS[$provider],
            'client_id' => $client->id,
            'scopes' => $this->scopesFor($provider),
            'extra' => $extra,
            'session_id' => $request->session()->getId(),
        ]);

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(isset(self::DRIVERS[$provider]), 404);

        $clientId = $request->session()->pull('social_oauth.client_id');
        $request->session()->forget('social_oauth.provider');
        abort_unless($clientId, 400, 'Sesión de OAuth expirada.');
        $client = Client::findOrFail($clientId);

        try {
            // El redirect_uri tiene que ser idéntico al que se usó al abrir el
            // diálogo; Meta valida que coincida al canjear el code.
            $socialUser = Socialite::driver(self::DRIVERS[$provider])
                ->redirectUrl($this->redirectUrlFor($provider))
                ->user();
            Log::info('[oauth-debug] callback:socialite-user', [
                'provider' => $provider,
                'driver' => self::DRIVERS[$provider],
                'client_id' => $client->id,
                'oauth_query' => [
                    'has_code' => $request->has('code'),
                    'has_state' => $request->has('state'),
                    'error' => $request->query('error'),
                    'error_description' => $request->query('error_description'),
                ],
                'socialite_user' => [
                    'id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'nickname' => $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'expires_in' => $socialUser->expiresIn ?? null,
                    'token' => $this->maskSecret($socialUser->token ?? null),
                    'refresh_token' => $this->maskSecret($socialUser->refreshToken ?? null),
                    'raw' => $this->sanitizePayload($this->socialUserRaw($socialUser)),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('[oauth-debug] callback:socialite-user-failed', [
                'provider' => $provider,
                'driver' => self::DRIVERS[$provider],
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->route('social.clients.show', $client->id)
                ->withErrors(['oauth' => 'No se pudo completar la autorización: ' . $e->getMessage()]);
        }

        try {
            $saved = match ($provider) {
                'facebook'  => $this->handleFacebook($client, $socialUser, $request),
                'instagram' => $this->handleInstagram($client, $socialUser, $request),
                'linkedin'  => $this->handleLinkedIn($client, $socialUser, $request),
                'tiktok'    => $this->handleTikTok($client, $socialUser, $request),
                'youtube'   => $this->handleYouTube($client, $socialUser, $request),
            };

            // Facebook/Instagram pueden pedir que el usuario elija qué activos
            // conectar antes de guardar nada.
            if ($saved instanceof RedirectResponse) {
                return $saved;
            }

            Log::info('[oauth-debug] callback:accounts-saved', [
                'provider' => $provider,
                'client_id' => $client->id,
                'saved_count' => count($saved),
                'saved_ids' => $saved,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('[oauth-debug] callback:save-failed', [
                'provider' => $provider,
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->route('social.clients.show', $client->id)
                ->withErrors(['oauth' => 'Error al guardar las cuentas: ' . $e->getMessage()]);
        }

        if (empty($saved)) {
            return redirect()->route('social.clients.show', $client->id)
                ->withErrors(['oauth' => $this->emptyResultMessage($provider)]);
        }

        $count = count($saved);
        $label = SocialAccount::query()->whereKey($saved[0])->value('provider');
        $msg   = $count === 1
            ? "Cuenta de {$label} conectada."
            : "{$count} cuentas de {$label} conectadas.";

        return redirect()->route('social.clients.show', $client->id)->with('success', $msg);
    }

    /**
     * Un usuario amarrado a un cliente (`users.client_id`) no puede conectar ni
     * desconectar cuentas de otro. Mismo criterio que SocialController.
     */
    private function autorizarCliente(Request $request, int $clientId): void
    {
        $propio = $request->user()?->client_id;

        abort_if($propio !== null && $propio !== $clientId, 403, 'Acceso denegado.');
    }

    public function disconnect(Request $request, SocialAccount $account)
    {
        $clientId = $account->client_id;
        $this->autorizarCliente($request, $clientId);
        $account->delete();

        return redirect()->route('social.clients.show', $clientId)
            ->with('success', 'Cuenta desconectada.');
    }

    // ---------------------------------------------------------------------
    // Scopes y parámetros extra por provider
    // ---------------------------------------------------------------------

    /**
     * Los scopes de Meta deben coincidir exactamente con los permisos que la
     * app tiene en App Review; pedir uno no aprobado hace que el diálogo lo
     * ignore o falle. Cola de App Review al 2026-08-14:
     *
     *   public_profile, pages_show_list, pages_read_engagement,
     *   pages_manage_posts, business_management,
     *   instagram_basic, instagram_content_publish,
     *   whatsapp_business_messaging, whatsapp_business_management
     *
     * Instagram va por Facebook Login ("API setup with Facebook login"), que es
     * la familia `instagram_basic` / `instagram_content_publish`. NO la de
     * `instagram_business_*`, que corresponde a Instagram Login y usa otro
     * flujo de OAuth.
     *
     * `email` se quitó: nunca guardamos el correo del usuario que autoriza
     * —guardamos páginas y cuentas IG— y no está en la solicitud de review.
     */
    private function scopesFor(string $provider): array
    {
        return match ($provider) {
            'facebook' => [
                'public_profile',
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_posts',
                'business_management',
            ],
            'instagram' => [
                'public_profile',
                'pages_show_list',
                'pages_read_engagement',
                'instagram_basic',
                'instagram_content_publish',
                'business_management',
            ],
            'linkedin' => [
                'openid',
                'profile',
                'email',
                'w_member_social',
                // Para listar y postear en organizaciones de empresa se requiere
                // aprobación de Marketing Developer Platform de LinkedIn:
                //   'r_organization_admin', 'w_organization_social'
                // Si tu app fue aprobada, agrégalos aquí.
            ],
            'tiktok' => [
                'user.info.basic',
                'video.publish',
                'video.upload',
            ],
            'youtube' => [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.upload',
            ],
        };
    }

    // ---------------------------------------------------------------------
    // Selección de activos
    // ---------------------------------------------------------------------

    /** Clave de sesión donde viajan los candidatos entre el callback y la selección. */
    private const SESION_PENDIENTES = 'social_oauth.pendientes';

    /**
     * Decide si guardar directo o pedirle al usuario que elija.
     *
     * Una misma cuenta de Facebook suele administrar páginas de VARIOS
     * clientes de la agencia. Guardar todo lo que alcanza el token mete
     * páginas ajenas en el cliente equivocado, y como el índice único es
     * (provider, provider_user_id), reasignarlas se las quita al cliente que
     * ya las tenía. Por eso, en cuanto hay más de un candidato —o el único
     * candidato pertenece hoy a otro cliente— se pregunta.
     */
    private function resolverCandidatos(Client $client, string $provider, array $candidatos, Request $request): array|RedirectResponse
    {
        if (empty($candidatos)) {
            return [];
        }

        $duenos = $this->duenosActuales($candidatos);

        $requiereEleccion = count($candidatos) > 1
            || collect($duenos)->contains(fn ($dueno) => $dueno !== null && $dueno['client_id'] !== $client->id);

        if (!$requiereEleccion) {
            return [$this->guardarCandidato($client, $candidatos[0], $request)];
        }

        // Los candidatos traen page tokens: se cifran antes de tocar la sesión,
        // que según el driver puede acabar en disco en texto plano.
        $request->session()->put(self::SESION_PENDIENTES, Crypt::encrypt([
            'client_id'  => $client->id,
            'provider'   => $provider,
            'candidatos' => $candidatos,
        ]));

        return redirect()->route('social.oauth.select', $client->id);
    }

    /**
     * Cliente al que pertenece hoy cada candidato, indexado por provider_user_id.
     */
    private function duenosActuales(array $candidatos): array
    {
        $cuentas = SocialAccount::query()
            ->whereIn('provider_user_id', array_column($candidatos, 'provider_user_id'))
            ->whereIn('provider', array_unique(array_column($candidatos, 'provider')))
            ->with('client:id,business_name')
            ->get();

        $duenos = [];
        foreach ($candidatos as $candidato) {
            $cuenta = $cuentas->first(fn ($c) => $c->provider === $candidato['provider']
                && $c->provider_user_id === $candidato['provider_user_id']);

            $duenos[$candidato['provider_user_id']] = $cuenta
                ? ['client_id' => $cuenta->client_id, 'client_name' => $cuenta->client?->business_name]
                : null;
        }

        return $duenos;
    }

    /** Pantalla donde el usuario elige qué activos conectar a este cliente. */
    public function selectAccounts(Request $request, Client $client)
    {
        $this->autorizarCliente($request, $client->id);

        $pendiente = $this->pendientes($request, $client);
        $duenos    = $this->duenosActuales($pendiente['candidatos']);

        return Inertia::render('Social/ConnectSelect', [
            'client'   => $client->only(['id', 'business_name']),
            'provider' => $pendiente['provider'],
            // Sin tokens: esto viaja al navegador.
            'accounts' => collect($pendiente['candidatos'])->map(fn ($c) => [
                'provider_user_id' => $c['provider_user_id'],
                'name'             => $c['name'],
                'handle'           => $c['handle'],
                'avatar_url'       => $c['avatar_url'],
                'owner'            => $duenos[$c['provider_user_id']] ?? null,
            ])->values(),
        ]);
    }

    /** Guarda únicamente los activos marcados. */
    public function storeSelection(Request $request, Client $client)
    {
        $this->autorizarCliente($request, $client->id);

        $datos = $request->validate([
            'provider_user_ids'   => 'required|array|min:1',
            'provider_user_ids.*' => 'string',
        ]);

        $pendiente = $this->pendientes($request, $client);

        $elegidos = collect($pendiente['candidatos'])
            ->whereIn('provider_user_id', $datos['provider_user_ids'])
            ->values();

        if ($elegidos->isEmpty()) {
            return back()->withErrors(['seleccion' => 'No se reconoció ninguna de las cuentas seleccionadas.']);
        }

        $ids = $elegidos->map(fn ($c) => $this->guardarCandidato($client, $c, $request))->all();

        $request->session()->forget(self::SESION_PENDIENTES);

        $etiqueta = $pendiente['provider'];
        $msg = count($ids) === 1
            ? "Cuenta de {$etiqueta} conectada."
            : count($ids) . " cuentas de {$etiqueta} conectadas.";

        return redirect()->route('social.clients.show', $client->id)->with('success', $msg);
    }

    /**
     * Recupera y descifra los candidatos, verificando que sean de este cliente.
     */
    private function pendientes(Request $request, Client $client): array
    {
        $crudo = $request->session()->get(self::SESION_PENDIENTES);
        abort_unless($crudo, 410, 'La selección expiró. Vuelve a conectar la red.');

        try {
            $pendiente = Crypt::decrypt($crudo);
        } catch (\Throwable $e) {
            report($e);
            abort(410, 'La selección expiró. Vuelve a conectar la red.');
        }

        abort_unless(($pendiente['client_id'] ?? null) === $client->id, 403, 'Acceso denegado.');

        return $pendiente;
    }

    private function guardarCandidato(Client $client, array $candidato, Request $request): int
    {
        return $this->upsertAccount(
            client:         $client,
            provider:       $candidato['provider'],
            providerUserId: $candidato['provider_user_id'],
            name:           $candidato['name'],
            handle:         $candidato['handle'],
            avatarUrl:      $candidato['avatar_url'],
            accessToken:    $candidato['access_token'],
            refreshToken:   null,
            expiresAt:      null,
            scopes:         $this->scopesFor($candidato['provider']),
            meta:           $candidato['meta'],
            connectedBy:    $request->user()?->id,
        );
    }

    /**
     * Callback propio de CADA provider lógico, no el del driver de Socialite.
     *
     * Instagram entra por el driver `facebook`, y Socialite arma el
     * redirect_uri desde `services.facebook.redirect`. Sin esto, el diálogo de
     * Instagram regresaba a /social/oauth/facebook/callback, el parámetro de
     * ruta llegaba como `facebook` y corría handleFacebook(): `handleInstagram()`
     * era inalcanzable y no se podía conectar ninguna cuenta de Instagram.
     *
     * Cada URL de estas debe estar dada de alta en Valid OAuth Redirect URIs.
     */
    private function redirectUrlFor(string $provider): string
    {
        return (string) (
            config("services.{$provider}.redirect")
            ?: config('services.' . self::DRIVERS[$provider] . '.redirect')
        );
    }

    /**
     * ID de la configuración de Facebook Login for Business, si está definida.
     * Solo aplica a los providers que entran por el OAuth de Meta.
     */
    private function loginConfigIdFor(string $provider): ?string
    {
        if (!in_array($provider, ['facebook', 'instagram'], true)) {
            return null;
        }

        $configId = (string) config("services.{$provider}.login_config_id", '');

        return $configId !== '' ? $configId : null;
    }

    private function extraParamsFor(string $provider): array
    {
        return match ($provider) {
            // Google: necesarios para recibir refresh_token cada vez.
            'youtube' => ['access_type' => 'offline', 'prompt' => 'consent'],
            // Facebook: permite re-solicitar permisos denegados previamente.
            'facebook', 'instagram' => ['auth_type' => 'rerequest'],
            default => [],
        };
    }

    // ---------------------------------------------------------------------
    // Handlers por provider
    // ---------------------------------------------------------------------

    /**
     * Facebook: NO guardar el usuario personal. Iterar /me/accounts y guardar
     * cada Page como su propia fila usando el page_access_token (no expira si
     * el user token es long-lived).
     */
    private function handleFacebook(Client $client, SocialiteUser $u, Request $request): array|RedirectResponse
    {
        $userToken = $this->exchangeFbLongLivedToken($u->token) ?? $u->token;
        $pages     = $this->fetchFacebookPages($userToken);

        Log::info('[oauth-debug] facebook:pages-response', [
            'client_id' => $client->id,
            'page_count' => count($pages),
            'pages' => $this->sanitizePayload($pages),
            'used_user_token' => $this->maskSecret($userToken),
        ]);

        if (empty($pages)) {
            return [];
        }

        $candidatos = [];
        foreach ($pages as $page) {
            $pageId    = (string) ($page['id'] ?? '');
            $pageToken = $page['access_token'] ?? null;
            if ($pageId === '' || !$pageToken) {
                continue;
            }

            // Sin page_token: el token va en access_token, que sí está cifrado
            // y oculto. `meta` viaja al frontend en el payload de Inertia.
            $meta = [
                'page_id'    => $pageId,
                'page_name'  => $page['name'] ?? null,
                'category'   => $page['category'] ?? null,
                'tasks'      => $page['tasks'] ?? null,
            ];
            if (!empty($page['instagram_business_account']['id'])) {
                $meta['ig_business_id'] = $page['instagram_business_account']['id'];
            }

            $candidatos[] = [
                'provider'         => 'facebook',
                'provider_user_id' => $pageId,
                'name'             => $page['name'] ?? 'Facebook Page',
                'handle'           => $page['username'] ?? null,
                'avatar_url'       => null,
                'access_token'     => $pageToken,     // page tokens son los útiles
                'meta'             => $meta,
            ];
        }

        return $this->resolverCandidatos($client, 'facebook', $candidatos, $request);
    }

    /**
     * Instagram: por cada Page que tenga instagram_business_account, crear
     * (o actualizar) una fila con provider='instagram' y provider_user_id =
     * ig_business_id. NO se guarda la Page de Facebook ni el usuario personal.
     */
    private function handleInstagram(Client $client, SocialiteUser $u, Request $request): array|RedirectResponse
    {
        $userToken = $this->exchangeFbLongLivedToken($u->token) ?? $u->token;
        $pages     = $this->fetchFacebookPages($userToken);

        Log::info('[oauth-debug] instagram:pages-response', [
            'client_id' => $client->id,
            'page_count' => count($pages),
            'pages' => $this->sanitizePayload($pages),
            'used_user_token' => $this->maskSecret($userToken),
        ]);

        $candidatos = [];
        foreach ($pages as $page) {
            $igId = $page['instagram_business_account']['id'] ?? null;
            if (!$igId) {
                continue;
            }
            $pageToken = $page['access_token'] ?? null;
            if (!$pageToken) {
                continue;
            }

            $igProfile = $this->fetchInstagramProfile((string) $igId, $pageToken);

            Log::info('[oauth-debug] instagram:business-profile', [
                'client_id' => $client->id,
                'page_id' => $page['id'] ?? null,
                'ig_business_id' => $igId,
                'ig_profile' => $this->sanitizePayload($igProfile),
                'page_token' => $this->maskSecret($pageToken),
            ]);

            // Sin page_token: ver el comentario en handleFacebook().
            $meta = [
                'ig_business_id' => (string) $igId,
                'page_id'        => $page['id'] ?? null,
                'page_name'      => $page['name'] ?? null,
                'ig_username'    => $igProfile['username'] ?? null,
            ];

            $candidatos[] = [
                'provider'         => 'instagram',
                'provider_user_id' => (string) $igId,
                'name'             => $igProfile['name'] ?? ($page['name'] ?? 'Instagram Business'),
                'handle'           => $igProfile['username'] ?? null,
                'avatar_url'       => $igProfile['profile_picture_url'] ?? null,
                'access_token'     => $pageToken,
                'meta'             => $meta,
            ];
        }

        return $this->resolverCandidatos($client, 'instagram', $candidatos, $request);
    }

    /**
     * LinkedIn: guarda el perfil personal y, si la app tiene los scopes de
     * organización aprobados (r_organization_admin), itera las organizaciones
     * donde el usuario es ADMINISTRATOR y guarda una fila por cada una.
     */
    private function handleLinkedIn(Client $client, SocialiteUser $u, Request $request): array
    {
        $raw = $u->getRaw();
        $sub = $raw['sub'] ?? $u->getId();

        Log::info('[oauth-debug] linkedin:profile-response', [
            'client_id' => $client->id,
            'profile' => $this->sanitizePayload($raw),
            'token' => $this->maskSecret($u->token),
        ]);

        $ids = [];

        // 1) Perfil personal
        $ids[] = $this->upsertAccount(
            client:         $client,
            provider:       'linkedin',
            providerUserId: (string) $sub,
            name:           $u->getName() ?? ($raw['name'] ?? 'LinkedIn'),
            handle:         $raw['preferred_username'] ?? null,
            avatarUrl:      $u->getAvatar(),
            accessToken:    $u->token,
            refreshToken:   $u->refreshToken ?? null,
            expiresAt:      isset($u->expiresIn) ? now()->addSeconds($u->expiresIn) : null,
            scopes:         $this->scopesFor('linkedin'),
            meta:           [
                'urn'   => "urn:li:person:{$sub}",
                'type'  => 'person',
                'email' => $u->getEmail(),
            ],
            connectedBy:    $request->user()?->id,
        );

        // 2) Organizaciones (requiere scope r_organization_admin aprobado).
        try {
            $orgs = $this->fetchLinkedInOrganizations($u->token);
            Log::info('[oauth-debug] linkedin:organizations-response', [
                'client_id' => $client->id,
                'organization_count' => count($orgs),
                'organizations' => $this->sanitizePayload($orgs),
            ]);
            foreach ($orgs as $org) {
                $orgId = $org['id'] ?? null;
                if (!$orgId) {
                    continue;
                }
                $ids[] = $this->upsertAccount(
                    client:         $client,
                    provider:       'linkedin',
                    providerUserId: "org:{$orgId}",   // distinto del provider_user_id personal
                    name:           $org['name'] ?? "LinkedIn Org {$orgId}",
                    handle:         $org['vanityName'] ?? null,
                    avatarUrl:      $org['logoUrl'] ?? null,
                    accessToken:    $u->token,
                    refreshToken:   $u->refreshToken ?? null,
                    expiresAt:      isset($u->expiresIn) ? now()->addSeconds($u->expiresIn) : null,
                    scopes:         $this->scopesFor('linkedin'),
                    meta:           [
                        'urn'             => "urn:li:organization:{$orgId}",
                        'type'            => 'organization',
                        'organization_id' => (string) $orgId,
                    ],
                    connectedBy:    $request->user()?->id,
                );
            }
        } catch (\Throwable $e) {
            // Sin scope r_organization_admin la API devuelve 403; lo registramos
            // pero no rompemos el flujo: el perfil personal sí quedó conectado.
            Log::info('LinkedIn organizations not available: ' . $e->getMessage());
        }

        return $ids;
    }

    private function handleTikTok(Client $client, SocialiteUser $u, Request $request): array
    {
        $raw = $u->getRaw();
        Log::info('[oauth-debug] tiktok:profile-response', [
            'client_id' => $client->id,
            'profile' => $this->sanitizePayload($raw),
            'token' => $this->maskSecret($u->token),
            'refresh_token' => $this->maskSecret($u->refreshToken ?? null),
        ]);
        return [$this->upsertAccount(
            client:         $client,
            provider:       'tiktok',
            providerUserId: (string) $u->getId(),
            name:           $u->getName() ?? $u->getNickname() ?? 'TikTok',
            handle:         $u->getNickname() ?? ($raw['data']['user']['username'] ?? null),
            avatarUrl:      $u->getAvatar(),
            accessToken:    $u->token,
            refreshToken:   $u->refreshToken ?? null,
            expiresAt:      isset($u->expiresIn) ? now()->addSeconds($u->expiresIn) : null,
            scopes:         $this->scopesFor('tiktok'),
            meta:           ['open_id' => $raw['data']['user']['open_id'] ?? null],
            connectedBy:    $request->user()?->id,
        )];
    }

    /**
     * YouTube: una fila por canal del usuario (mine=true normalmente devuelve uno,
     * pero brand accounts pueden tener varios).
     */
    private function handleYouTube(Client $client, SocialiteUser $u, Request $request): array
    {
        $channels = [];
        try {
            $channels = Http::withToken($u->token)
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'snippet,contentDetails',
                    'mine' => 'true',
                ])->throw()->json('items', []);
            Log::info('[oauth-debug] youtube:channels-response', [
                'client_id' => $client->id,
                'channel_count' => count($channels),
                'channels' => $this->sanitizePayload($channels),
                'token' => $this->maskSecret($u->token),
                'refresh_token' => $this->maskSecret($u->refreshToken ?? null),
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('[oauth-debug] youtube:channels-failed', [
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);
        }

        $ids = [];
        foreach ($channels as $ch) {
            $channelId = $ch['id'] ?? null;
            if (!$channelId) {
                continue;
            }
            $snippet = $ch['snippet'] ?? [];
            $ids[] = $this->upsertAccount(
                client:         $client,
                provider:       'youtube',
                providerUserId: (string) $channelId,
                name:           $snippet['title'] ?? 'YouTube Channel',
                handle:         $snippet['customUrl'] ?? null,
                avatarUrl:      $snippet['thumbnails']['default']['url'] ?? null,
                accessToken:    $u->token,
                refreshToken:   $u->refreshToken ?? null,   // CRÍTICO: Google rota tokens; refresh_token es lo que persiste
                expiresAt:      isset($u->expiresIn) ? now()->addSeconds($u->expiresIn) : null,
                scopes:         $this->scopesFor('youtube'),
                meta:           [
                    'channel_id'    => $channelId,
                    'channel_title' => $snippet['title'] ?? null,
                    'uploads_id'    => $ch['contentDetails']['relatedPlaylists']['uploads'] ?? null,
                ],
                connectedBy:    $request->user()?->id,
            );
        }

        // Fallback: si el endpoint falló pero tenemos token, al menos guardamos
        // una fila con el sub de Google para no perder el refresh_token.
        if (empty($ids)) {
            $ids[] = $this->upsertAccount(
                client:         $client,
                provider:       'youtube',
                providerUserId: (string) $u->getId(),
                name:           $u->getName() ?? 'YouTube',
                handle:         null,
                avatarUrl:      $u->getAvatar(),
                accessToken:    $u->token,
                refreshToken:   $u->refreshToken ?? null,
                expiresAt:      isset($u->expiresIn) ? now()->addSeconds($u->expiresIn) : null,
                scopes:         $this->scopesFor('youtube'),
                meta:           ['note' => 'channel lookup failed; refresh_token preserved'],
                connectedBy:    $request->user()?->id,
            );
        }
        return $ids;
    }

    // ---------------------------------------------------------------------
    // Helpers HTTP
    // ---------------------------------------------------------------------

    private function fetchFacebookPages(string $userToken): array
    {
        $version = config('services.facebook.graph_version', 'v21.0');
        $url     = "https://graph.facebook.com/{$version}/me/accounts";
        $params  = [
            'access_token' => $userToken,
            'fields'       => 'id,name,username,category,tasks,access_token,instagram_business_account{id,username}',
            'limit'        => 100,
        ];

        $pages = [];
        $next  = $url;
        $first = true;

        // Paginación cursor de Graph.
        while ($next) {
            $resp = $first
                ? Http::get($next, $params)
                : Http::get($next);
            $resp->throw();
            $body  = $resp->json();
            $pages = array_merge($pages, $body['data'] ?? []);
            $next  = $body['paging']['next'] ?? null;
            $first = false;
        }
        return $pages;
    }

    private function fetchInstagramProfile(string $igId, string $pageToken): array
    {
        try {
            $version = config('services.facebook.graph_version', 'v21.0');
            return Http::get("https://graph.facebook.com/{$version}/{$igId}", [
                'access_token' => $pageToken,
                'fields'       => 'id,username,name,profile_picture_url',
            ])->throw()->json() ?: [];
        } catch (\Throwable $e) {
            Log::info("IG profile fetch failed for {$igId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Intercambia un short-lived user token por un long-lived (60d).
     * Devuelve null si falla; el caller hará fallback al token original.
     */
    private function exchangeFbLongLivedToken(string $shortToken): ?string
    {
        $appId     = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');
        $version   = config('services.facebook.graph_version', 'v21.0');
        if (!$appId || !$appSecret) {
            return null;
        }
        try {
            $resp = Http::get("https://graph.facebook.com/{$version}/oauth/access_token", [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $shortToken,
            ])->throw()->json();
            Log::info('[oauth-debug] facebook:long-lived-token-response', [
                'received_access_token' => isset($resp['access_token']),
                'expires_in' => $resp['expires_in'] ?? null,
                'token' => $this->maskSecret($resp['access_token'] ?? null),
            ]);
            return $resp['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('FB long-lived token exchange failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Devuelve las organizaciones donde el usuario es ADMINISTRATOR.
     * Requiere scope r_organization_admin aprobado en LinkedIn.
     */
    private function fetchLinkedInOrganizations(string $token): array
    {
        $aclResp = Http::withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get('https://api.linkedin.com/v2/organizationAcls', [
                'q'     => 'roleAssignee',
                'role'  => 'ADMINISTRATOR',
                'state' => 'APPROVED',
            ])->throw()->json();

        $orgs = [];
        foreach (($aclResp['elements'] ?? []) as $el) {
            $orgUrn = $el['organization'] ?? null;          // urn:li:organization:12345
            if (!$orgUrn || !preg_match('/urn:li:organization:(\d+)/', $orgUrn, $m)) {
                continue;
            }
            $orgId = $m[1];

            // Detalle de la organización (nombre y vanityName).
            $detail = [];
            try {
                $detail = Http::withToken($token)
                    ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
                    ->get("https://api.linkedin.com/v2/organizations/{$orgId}")
                    ->throw()->json() ?: [];
            } catch (\Throwable $e) {
                Log::info("LinkedIn org {$orgId} detail failed: " . $e->getMessage());
            }

            $orgs[] = [
                'id'         => $orgId,
                'name'       => $detail['localizedName']
                                ?? $detail['name']['localized']['en_US']
                                ?? "Organization {$orgId}",
                'vanityName' => $detail['vanityName'] ?? null,
                'logoUrl'    => null, // logoV2 requiere otra llamada; omitido por simplicidad.
            ];
        }
        return $orgs;
    }

    // ---------------------------------------------------------------------
    // Upsert
    // ---------------------------------------------------------------------

    /**
     * Devuelve el id del SocialAccount upserted.
     */
    private function upsertAccount(
        Client $client,
        string $provider,
        string $providerUserId,
        string $name,
        ?string $handle,
        ?string $avatarUrl,
        string $accessToken,
        ?string $refreshToken,
        ?\Carbon\CarbonInterface $expiresAt,
        array $scopes,
        array $meta,
        ?int $connectedBy,
    ): int {
        // Si NO recibimos refresh_token nuevo pero ya teníamos uno guardado, lo
        // conservamos (importante para Google/YouTube, que sólo devuelve el
        // refresh_token la primera vez salvo prompt=consent forzado).
        if ($refreshToken === null) {
            // Vía modelo, no ->value(): la columna está cifrada y hace falta el
            // cast para recuperar el valor en claro antes de volver a guardarlo.
            $existingRefresh = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first()?->refresh_token;
            if ($existingRefresh) {
                $refreshToken = $existingRefresh;
            }
        }

        $account = SocialAccount::updateOrCreate(
            [
                'provider'         => $provider,
                'provider_user_id' => $providerUserId,
            ],
            [
                'client_id'        => $client->id,
                'name'             => $name,
                'handle'           => $handle,
                'avatar_url'       => $avatarUrl,
                'access_token'     => $accessToken,
                'refresh_token'    => $refreshToken,
                'token_expires_at' => $expiresAt,
                'scopes'           => $scopes,
                'meta'             => $meta,
                'status'           => 'active',
                'connected_by'     => $connectedBy,
                'last_synced_at'   => now(),
            ]
        );

        return $account->id;
    }

    private function emptyResultMessage(string $provider): string
    {
        return match ($provider) {
            'facebook'  => 'No se encontraron páginas de Facebook administradas con esta cuenta. Asegúrate de tener rol de administrador en al menos una página.',
            'instagram' => 'No se encontraron cuentas de Instagram Business vinculadas a tus páginas de Facebook.',
            'youtube'   => 'No se encontraron canales de YouTube en esta cuenta de Google.',
            default     => 'No se conectó ninguna cuenta.',
        };
    }

    private function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }

    private function socialUserRaw(SocialiteUser $user): array
    {
        if (method_exists($user, 'getRaw')) {
            $raw = $user->getRaw();
            return is_array($raw) ? $raw : [];
        }

        $fallback = $user->user ?? [];
        return is_array($fallback) ? $fallback : [];
    }

    private function sanitizePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $out = [];
            foreach ($payload as $key => $value) {
                $lower = is_string($key) ? strtolower($key) : '';
                if (is_string($key) && (
                    str_contains($lower, 'token') ||
                    str_contains($lower, 'secret') ||
                    str_contains($lower, 'authorization')
                )) {
                    $out[$key] = is_string($value) ? $this->maskSecret($value) : '[redacted]';
                    continue;
                }
                $out[$key] = $this->sanitizePayload($value);
            }
            return $out;
        }

        if (is_object($payload)) {
            return $this->sanitizePayload((array) $payload);
        }

        return $payload;
    }
}
