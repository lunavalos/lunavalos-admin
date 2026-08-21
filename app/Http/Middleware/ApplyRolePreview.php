<?php

namespace App\Http\Middleware;

use App\Support\RolePreview;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica el modo "Ver como rol" al usuario autenticado.
 *
 * Va en el grupo `web` ANTES de HandleInertiaRequests: las props que Inertia
 * comparte (is_admin, permissions, can) deben calcularse ya con el rol
 * previsualizado, o el menú mostraría lo del rol real.
 */
class ApplyRolePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($user = $request->user()) {
                RolePreview::apply($user);
            }
        } catch (\Throwable $e) {
            // Nunca dejamos que el modo depuración tumbe un request.
        }

        return $next($request);
    }
}
