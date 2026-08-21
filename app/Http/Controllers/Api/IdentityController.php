<?php

namespace App\Http\Controllers\Api;

use App\Models\WhatsAppNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * GET /api/v1/yo — qué es este token.
 *
 * Parece un extra y es lo primero que se necesita: al integrar un sistema
 * nuevo, la pregunta siempre es "¿estoy usando el token correcto y sobre qué
 * cliente pega?". Sin esto se descubre mandando un mensaje de prueba a un
 * contacto real.
 */
class IdentityController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $consumidor = $this->consumidor($request);

        /** @var PersonalAccessToken $token */
        $token = $consumidor->currentAccessToken();

        // Los números que este consumidor puede usar. Para uno atado son los de
        // su cliente; uno interno tendría que nombrar el cliente en cada
        // petición, así que aquí no se listan.
        $numeros = $consumidor->estaAtado()
            ? WhatsAppNumber::where('is_active', true)
                ->where('client_id', $consumidor->client_id)
                ->get()
            : collect();

        return $this->ok([
            'integracion' => [
                'nombre' => $consumidor->name,
                'slug'   => $consumidor->slug,
                'estado' => $consumidor->status,
            ],
            'alcance' => [
                // `atado` es el dato que resuelve la confusión más común: si es
                // false, toda petición necesita `client_id`.
                'atado'     => $consumidor->estaAtado(),
                'client_id' => $consumidor->client_id,
                'cliente'   => $consumidor->client?->business_name,
            ],
            'token' => [
                'nombre'     => $token?->name,
                'permisos'   => $token?->abilities ?? [],
                'expira_el'  => $token?->expires_at?->toIso8601String(),
            ],
            'numeros' => $numeros->map(fn (WhatsAppNumber $n) => [
                'phone_number_id'      => $n->phone_number_id,
                'display_phone_number' => $n->display_phone_number,
                'quality_rating'       => $n->quality_rating,
            ])->all(),
            'recibe_webhooks' => $consumidor->recibeWebhooks(),
        ]);
    }
}
