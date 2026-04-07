<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated and has 2FA enabled but NOT verified in this session
        if ($user && $user->twoFactorAuth?->enabled && !$request->session()->has('two_factor_auth_passed')) {
            // Log out user for safety? No, if we log out, then the session is destroyed.
            // But we want to preserve the fact they are logged in but NOT verified.
            // This is safer if we use a special gate.
            // Actually, many implement it by logging OUT and storing a "pre-auth" ID.
            
            // Let's do the "logout and pre-auth" pattern which is safer for Breeze.
            
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->put('two_factor_user_id', $user->id);
            
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
