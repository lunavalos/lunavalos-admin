<?php

namespace App\Http\Middleware;

use App\Models\ApiConsumer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la puerta a un token cuyo consumidor ya no debería entrar.
 *
 * Sanctum valida que el token exista y no haya caducado, pero no sabe nada de
 * nuestro `status`. Sin esto, desactivar una integración obligaría a borrar sus
 * tokens uno por uno, y un token olvidado seguiría funcionando.
 *
 * También descarta un token cuyo dueño no sea un ApiConsumer. Hoy no hay otro
 * modelo con HasApiTokens, pero el día que un `User` lo tenga, sus tokens no
 * deben abrir /api/v1 por accidente.
 */
class EnsureApiConsumerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $consumidor = $request->user();

        if (!$consumidor instanceof ApiConsumer) {
            return response()->json([
                'error' => [
                    'code'    => 'token_invalido',
                    'message' => 'Este token no pertenece a una integración de la plataforma.',
                ],
            ], 403);
        }

        if (!$consumidor->estaActivo()) {
            return response()->json([
                'error' => [
                    'code'    => 'consumidor_inactivo',
                    'message' => "La integración «{$consumidor->name}» está desactivada.",
                ],
            ], 403);
        }

        // Para poder responder "¿esta integración sigue viva?" sin revisar logs.
        // `forceFill` + `saveQuietly` para no tocar updated_at ni disparar
        // eventos en cada petición.
        $consumidor->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
