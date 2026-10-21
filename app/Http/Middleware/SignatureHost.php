<?php

namespace App\Http\Middleware;

use App\Support\SignatureUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El subdominio de firmas solo sirve firmas.
 *
 * Es la misma app, así que sin esto `firmas.lunavalos.com/login` mostraría el
 * login del admin: justo lo que el subdominio busca no exponer en los correos.
 */
class SignatureHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = SignatureUrls::host();

        if ($host && $request->getHost() === $host && ! $request->is('firma/*')) {
            abort(404);
        }

        return $next($request);
    }
}
