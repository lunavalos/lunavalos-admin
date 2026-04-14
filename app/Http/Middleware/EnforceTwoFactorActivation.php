<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTwoFactorActivation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario está autenticado y NO ha activado el 2FA
        if ($user && !$user->hasTwoFactorEnabled()) {
            
            // Permitir acceso a las rutas de perfil y activación de 2FA, así como el cierre de sesión
            if (!$request->routeIs('profile.*') && 
                !$request->routeIs('password.*') && 
                !$request->routeIs('verification.*') && 
                !$request->routeIs('two-factor.*') && 
                !$request->routeIs('logout')) {
                
                return redirect()->route('profile.edit')->with('warning', 'Por razones de seguridad, es obligatorio activar la autenticación de dos factores (2FA) para acceder a su panel.');
            }
        }

        return $next($request);
    }
}
