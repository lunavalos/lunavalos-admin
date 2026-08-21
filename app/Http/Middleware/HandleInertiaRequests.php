<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $notifications = [];
        $user = $request->user();

        if ($user) {
            try {
                $notifications = $user->unreadNotifications;
            } catch (\Throwable $e) {
                $notifications = [];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? (function () use ($user) {
                    // Resolve roles/permissions safely — Spatie can throw if permissions
                    // table doesn't exist yet or cache is stale (common after fresh deploy).
                    $isClient = false;
                    $isAdmin  = false;
                    $permissions = collect();

                    try {
                        $isClient    = $user->isClient();
                        $isAdmin     = $user->isAdmin();
                        $permissions = $user->getAllPermissions()->pluck('name');
                    } catch (\Throwable $e) {
                        // Spatie not ready — leave as safe defaults
                    }

                    // Build `can` map — admins get full access as fallback
                    $canMap = [];
                    try {
                        $canMap = [
                            'view_roles'    => $user->can('Ver Roles'),
                            'view_users'    => $user->can('Ver Usuarios'),
                            'view_services' => $user->can('Ver Servicios'),
                            'view_clients'  => $user->can('Ver Clientes'),
                            'view_quotes'   => $user->can('Ver Cotizaciones'),
                            'view_reports'  => $user->can('Ver Reportes'),
                        ];
                    } catch (\Throwable $e) {
                        // Spatie permissions missing — grant all to admins, deny to others
                        $canMap = [
                            'view_roles'    => $isAdmin,
                            'view_users'    => $isAdmin,
                            'view_services' => $isAdmin,
                            'view_clients'  => $isAdmin,
                            'view_quotes'   => $isAdmin,
                            'view_reports'  => $isAdmin,
                        ];
                    }

                    $hasCustomEmail = false;
                    try {
                        $hasCustomEmail = $user->client ? $user->client->has_custom_email_config : false;
                    } catch (\Throwable $e) {}

                    return array_merge($user->toArray(), [
                        'is_client'               => $isClient,
                        'is_admin'                => $isAdmin,
                        'permissions'             => $permissions,
                        'can'                     => $canMap,
                        'has_custom_email_config' => $hasCustomEmail,
                    ]);
                })() : null,
                'notifications' => $notifications,
            ],
            // Estado del modo "Ver como rol" (App\Support\RolePreview). Se
            // calcula con los roles REALES del usuario, no con los del preview:
            // si no, un admin viendo como "Cliente" perdería el control para salir.
            'role_preview' => fn () => \App\Support\RolePreview::state($user),

            // Catálogo de clientes para acotar el preview. Va como prop
            // opcional: solo se resuelve cuando el front la pide con un
            // `router.reload({ only: [...] })` al abrir el selector, así que
            // los 70+ clientes no viajan en cada visita.
            'role_preview_clients' => \Inertia\Inertia::optional(function () use ($user) {
                if (! \App\Support\RolePreview::canBindClient($user)) {
                    return [];
                }

                return \App\Models\Client::query()
                    ->orderBy('business_name')
                    ->get(['id', 'business_name'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->business_name])
                    ->all();
            }),
            'flash' => [
                'success' => $request->session()->get('success'),
                'message' => $request->session()->get('message'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                // El prompt ya armado que devuelve AiAgentController::preview().
                // Va por flash y no por prop de la página para que no viaje en
                // cada visita: solo aparece cuando se pide verlo.
                'preview' => $request->session()->get('preview'),
                // Token y secreto recién emitidos de una integración. Flash y
                // nada más que flash: Sanctum solo guarda el hash del token, así
                // que este es el único momento en que existe en claro. Como prop
                // de página viajaría en cada visita a la pantalla.
                'credenciales' => $request->session()->get('credenciales'),
            ],
            // Configuración de divisas para el composable useMoney() del frontend.
            'currencies' => [
                'base'      => config('currencies.base'),
                'default'   => config('currencies.default'),
                'supported' => config('currencies.supported'),
                // Tasas vigentes (de la moneda base a cada divisa soportada).
                // Permite al frontend convertir sin round-trips. Usamos `Closure`
                // para evaluarlo perezosamente solo cuando Inertia hace render.
                'rates'     => fn () => $this->latestRatesMap(),
            ],
        ];
    }

    /**
     * Devuelve el mapa { CURRENCY => rate-to-base } con la última tasa disponible.
     * La base contra sí misma siempre es 1.
     * Se cachea por 30 min para no consultar `exchange_rates` en cada request.
     */
    protected function latestRatesMap(): array
    {
        $base = (string) config('currencies.base', 'MXN');
        $supported = array_keys((array) config('currencies.supported', []));

        return \Illuminate\Support\Facades\Cache::remember(
            'inertia.fx.rates.' . $base,
            now()->addMinutes(30),
            function () use ($base, $supported) {
                $out = [$base => 1.0];
                foreach ($supported as $code) {
                    if ($code === $base) continue;
                    // Buscamos la tasa más reciente FROM=$code TO=$base.
                    $row = \App\Models\ExchangeRate::query()
                        ->where('from_currency', $code)
                        ->where('to_currency', $base)
                        ->orderByDesc('rate_date')
                        ->first();
                    $out[$code] = $row ? (float) $row->rate : null;
                }
                return $out;
            }
        );
    }
}
