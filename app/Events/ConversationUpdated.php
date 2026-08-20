<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cambió algo que la bandeja muestra: llegó un mensaje, se respondió, se
 * archivó.
 *
 * Es hermano de ConversationMessageSent pero resuelve otro problema. Aquel
 * viaja por `conversation.{id}` y solo lo escucha quien tiene ESA conversación
 * abierta; sirve para pintar la burbuja en el hilo. Éste viaja por un canal de
 * bandeja y actualiza la lista aunque no tengas nada abierto — sin él, un
 * mensaje que llega a otra conversación no se ve hasta recargar.
 *
 * Va a dos canales a propósito. El staff interno ve todas las conversaciones,
 * así que suscribirlo a un canal por cliente le obligaría a abrir tantas
 * suscripciones como clientes haya; escucha `conversations.internal` y recibe
 * todo. Un usuario de portal escucha solo el de su cliente.
 */
class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation)
    {
        $this->conversation->loadMissing(['client:id,business_name', 'assignee:id,name']);
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $canales = [new PrivateChannel('conversations.internal')];

        // Las conversaciones del número propio de LunAvalos tienen client_id
        // null: no hay canal de cliente al que mandarlas, y no debe haberlo.
        if ($this->conversation->client_id) {
            $canales[] = new PrivateChannel(
                'conversations.client.' . $this->conversation->client_id
            );
        }

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * La misma forma que produce ConversationController::bandeja(), para que el
     * front pueda insertar o reemplazar la fila sin traducir nada.
     */
    public function broadcastWith(): array
    {
        $c = $this->conversation;

        return [
            'id'              => $c->id,
            'contact_wa_id'   => $c->contact_wa_id,
            'contact_name'    => $c->contact_name,
            'status'          => $c->status,
            'unread_count'    => $c->unread_count,
            'client'          => $c->client?->only(['id', 'business_name']),
            'assignee'        => $c->assignee?->only(['id', 'name']),
            'ventana_abierta' => $c->ventanaAbierta(),
            'last_message_at' => $c->last_message_at?->toISOString(),
        ];
    }
}
