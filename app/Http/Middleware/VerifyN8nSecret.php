<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * El webhook de WhatsApp crea tickets y mensajes sin sesión ni CSRF, así que
 * la única barrera es este secreto compartido con n8n. La firma de Meta
 * (X-Hub-Signature-256) la valida n8n antes de reenviarnos el evento.
 */
class VerifyN8nSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.n8n.shared_secret');
        $received = (string) $request->header('X-N8n-Secret', '');

        // Sin secreto configurado se rechaza todo: dejar el endpoint abierto
        // por una variable de entorno faltante es peor que tirar el webhook.
        if ($expected === '' || !hash_equals($expected, $received)) {
            Log::warning('n8n: webhook rechazado', [
                'ip'         => $request->ip(),
                'path'       => $request->path(),
                'sin_config' => $expected === '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
