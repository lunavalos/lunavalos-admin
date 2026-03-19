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
            $notifications = $user->unreadNotifications;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'is_client' => $request->user()->hasRole('Cliente'),
                    'is_admin' => $request->user()->hasAnyRole(['Administrador', 'Administrador Master']),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    'can' => [
                        'view_roles' => $request->user()->can('Ver Roles'),
                        'view_users' => $request->user()->can('Ver Usuarios'),
                        'view_services' => $request->user()->can('Ver Servicios'),
                        'view_clients' => $request->user()->can('Ver Clientes'),
                        'view_quotes' => $request->user()->can('Ver Cotizaciones') ?? true, // optional since quotes might not have perm
                        // 'view_tasks' => $request->user()->can('Ver Tareas'),
                    ],
                    'has_custom_email_config' => $request->user()->client ? $request->user()->client->has_custom_email_config : false,
                ]) : null,
                'notifications' => $notifications,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'message' => $request->session()->get('message'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
