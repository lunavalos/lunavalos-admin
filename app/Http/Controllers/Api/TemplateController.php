<?php

namespace App\Http\Controllers\Api;

use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las plantillas que un sistema externo puede enviar.
 *
 * Solo lectura a propósito. Crear una plantilla usa
 * `whatsapp_business_management` y arrastra el flujo de aprobación de Meta con
 * sus ejemplos por variable; es una acción de operación, no de integración, y
 * vive en la pantalla del admin. Aquí solo se listan las que ya se pueden usar.
 */
class TemplateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'client_id'       => 'sometimes|integer',
            'phone_number_id' => 'sometimes|string',
            'todas'           => 'sometimes|boolean',
        ]);

        $clienteId = $this->clienteId($request);
        $numero    = $this->numeroDeEnvio($request, $clienteId);

        $plantillas = WhatsAppTemplate::where('whatsapp_account_id', $numero->whatsapp_account_id)
            // Por omisión solo las aprobadas: son las únicas que se pueden
            // mandar, y ofrecer las demás solo produce envíos fallidos. Con
            // `todas=1` salen todas, que es lo que hace falta para diagnosticar
            // "¿por qué no aparece la que acabo de crear?".
            ->when(
                !$request->boolean('todas'),
                fn ($q) => $q->where('status', WhatsAppTemplate::STATUS_APPROVED),
            )
            ->orderBy('name')
            ->get();

        return $this->ok([
            'data' => $plantillas->map(fn (WhatsAppTemplate $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'language'        => $p->language,
                'category'        => $p->category,
                'status'          => $p->status,
                'rejected_reason' => $p->rejected_reason,
                // Cuántos valores hay que mandar en `parametros`, y cómo se ve
                // el texto: sin esto el llamador tiene que adivinar el contrato.
                'body'            => $p->cuerpo(),
                'body_variables'  => $p->body_variables,
            ])->all(),
            'numero' => $this->serializarNumero($numero),
        ]);
    }

    private function serializarNumero(WhatsAppNumber $numero): array
    {
        return [
            'phone_number_id'      => $numero->phone_number_id,
            'display_phone_number' => $numero->display_phone_number,
            'verified_name'        => $numero->verified_name,
            'quality_rating'       => $numero->quality_rating,
        ];
    }
}
