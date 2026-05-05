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
                        $isClient    = $user->hasRole('Cliente');
                        $isAdmin     = $user->hasAnyRole(['Administrador', 'Administrador Master']);
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
                            'view_quotes'   => $user->can('Ver Cotizaciones') ?? true,
                            'view_reports'  => $user->can('Ver Reportes'),
                        ];
                    } catch (\Throwable $e) {
                        // Spatie permissions missing — grant all to admins, deny to others
                        $canMap = [
                            'view_roles'    => $isAdmin,
                            'view_users'    => $isAdmin,
                            'view_services' => $isAdmin,
                            'view_clients'  => $isAdmin,
                            'view_quotes'   => true,
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
            'flash' => [
                'success' => $request->session()->get('success'),
                'message' => $request->session()->get('message'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
        ];
    }
}
