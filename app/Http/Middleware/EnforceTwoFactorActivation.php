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

        // Si el usuario está autenticado, es Cliente y NO ha activado el 2FA
        if ($user && $user->hasRole('Cliente') && !$user->hasTwoFactorEnabled()) {
            
            // Permitir hasta 3 inicios de sesión antes de obligar
            if ($user->login_count > 3) {
                // Permitir acceso a las rutas de perfil y activación de 2FA, así como el cierre de sesión
                if (!$request->routeIs('profile.*') && 
                    !$request->routeIs('password.*') && 
                    !$request->routeIs('verification.*') && 
                    !$request->routeIs('two-factor.*') && 
                    !$request->routeIs('logout')) {
                    
                    return redirect()->route('profile.edit')->with('warning', 'Su periodo de gracia para configurar la autenticación de dos factores (2FA) ha terminado. Por razones de seguridad, es obligatorio activarlo para continuar.');
                }
            }
        } elseif ($user && !$user->hasRole('Cliente') && !$user->hasTwoFactorEnabled()) {
             // For non-clients (staff), maybe keep it mandatory from the start?
             // The previous logic was mandatory for everyone.
             // "Algunos clientes están teniendo problemas..." -> This strongly suggests the change is for clients.
             // I'll keep the mandatory behavior for staff for security.
             
             if (!$request->routeIs('profile.*') && 
                !$request->routeIs('password.*') && 
                !$request->routeIs('verification.*') && 
                !$request->routeIs('two-factor.*') && 
                !$request->routeIs('logout')) {
                
                return redirect()->route('profile.edit')->with('warning', 'Es obligatorio activar la autenticación de dos factores (2FA) para acceder a su panel.');
            }
        }

        return $next($request);
    }
}
