<?php

namespace App\Http\Controllers\Api;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lectura de conversaciones para un sistema externo.
 *
 * Sirve para que klwebapp muestre el hilo de un contacto dentro de su propia
 * interfaz sin duplicar el almacenamiento: la conversación vive aquí, ellos la
 * consultan.
 */
class ConversationApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'client_id' => 'sometimes|integer',
            'status'    => 'sometimes|string|in:open,snoozed,archived',
            'contacto'  => 'sometimes|string|max:32',
            'por_pagina' => 'sometimes|integer|min:1|max:100',
        ]);

        $conversaciones = $this->alcance($request)
            ->when(
                isset($datos['status']),
                fn ($q) => $q->where('status', $datos['status']),
            )
            ->when(
                isset($datos['contacto']),
                fn ($q) => $q->where('contact_wa_id', $this->normalizarWaId($datos['contacto'])),
            )
            ->orderByDesc('last_message_at')
            ->paginate($datos['por_pagina'] ?? 25);

        return $this->ok([
            'data' => collect($conversaciones->items())
                ->map(fn (Conversation $c) => $this->serializar($c))
                ->all(),
            'meta' => [
                'pagina'      => $conversaciones->currentPage(),
                'por_pagina'  => $conversaciones->perPage(),
                'total'       => $conversaciones->total(),
                'ultima_pagina' => $conversaciones->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $conversacion): JsonResponse
    {
        // Se busca DENTRO del alcance, no se busca y luego se compara: así un
        // id de otro cliente devuelve 404 y no confirma que exista.
        $encontrada = $this->alcance($request)->find($conversacion);

        if (!$encontrada) {
            $this->fallar('no_encontrada', 'Esa conversación no existe para esta integración.', 404);
        }

        $mensajes = $encontrada->messages()->orderBy('id')->limit(200)->get();

        return $this->ok($this->serializar($encontrada) + [
            'mensajes' => $mensajes->map(fn ($m) => [
                'id'              => $m->id,
                'author_type'     => $m->author_type,
                'direction'       => $m->direction,
                'type'            => $m->type,
                'body'            => $m->body,
                'delivery_status' => $m->delivery_status,
                'delivery_error'  => $m->delivery_error,
                'created_at'      => $m->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Las conversaciones que este consumidor puede ver. Un consumidor atado
     * solo ve las de su cliente; uno interno tiene que nombrar cuál.
     */
    private function alcance(Request $request)
    {
        return Conversation::where('client_id', $this->clienteId($request));
    }

    private function serializar(Conversation $c): array
    {
        return [
            'id'              => $c->id,
            'contact_wa_id'   => $c->contact_wa_id,
            'contact_name'    => $c->contact_name,
            'status'          => $c->status,
            'unread_count'    => $c->unread_count,
            'ai_enabled'      => $c->ai_enabled,
            // Lo que el llamador necesita para saber si puede mandar texto
            // libre o tiene que ir por plantilla, sin replicar la regla.
            'ventana_abierta' => $c->ventanaAbierta(),
            'last_inbound_at' => $c->last_inbound_at?->toIso8601String(),
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ];
    }
}
