<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica los webhooks que Meta manda directo a este sistema.
 *
 * El webhook crea tickets y mensajes sin sesión ni CSRF, así que la única
 * barrera es la firma: HMAC-SHA256 del cuerpo con el App Secret de la app de
 * Meta, que llega en X-Hub-Signature-256.
 *
 * Sustituye a VerifyN8nSecret: n8n ya no está en el camino de entrada.
 */
class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret   = (string) config('services.whatsapp.app_secret');
        $recibida = (string) $request->header('X-Hub-Signature-256', '');

        // Sin App Secret configurado se rechaza todo: dejar el endpoint abierto
        // por una variable de entorno faltante es peor que tirar el webhook.
        if ($secret === '' || $recibida === '') {
            return $this->rechazar($request, $secret === '' ? 'sin_config' : 'sin_firma');
        }

        // Sobre el cuerpo CRUDO, nunca sobre el JSON reserializado: cualquier
        // diferencia de orden o de escapes cambia el hash y rompe la firma.
        $esperada = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($esperada, $recibida)) {
            return $this->rechazar($request, 'firma_invalida');
        }

        return $next($request);
    }

    private function rechazar(Request $request, string $motivo): Response
    {
        Log::warning('meta: webhook rechazado', [
            'ip'     => $request->ip(),
            'path'   => $request->path(),
            'motivo' => $motivo,
        ]);

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
