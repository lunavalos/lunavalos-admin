<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel: any authenticated user viewing this ticket can receive live messages
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    return auth()->check(); // open to any logged-in user — adjust if you need finer control
});

/**
 * Conversaciones de WhatsApp. A diferencia del canal de tickets, éste sí filtra:
 * aquí viajan mensajes de clientes finales de terceros, y un usuario de portal
 * solo puede escuchar los de su propio cliente.
 */
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversacion = \App\Models\Conversation::find($conversationId);

    if (!$conversacion) {
        return false;
    }

    return $user->client_id === null
        || $user->client_id === $conversacion->client_id;
});


/**
 * Canales de la bandeja. Actualizan la LISTA, no un hilo concreto: sin ellos,
 * un mensaje que llega a una conversación que no tienes abierta no se ve hasta
 * recargar la pantalla.
 *
 * Son dos porque los dos públicos son distintos. El staff interno
 * (`client_id` null) ve todas las conversaciones, así que un canal por cliente
 * le obligaría a abrir tantas suscripciones como clientes haya: escucha
 * `conversations.internal` y le llega todo. Un usuario de portal escucha solo
 * el de su cliente, y aquí es donde se impide que oiga los de otro.
 */
Broadcast::channel('conversations.internal', \App\Broadcasting\InternalInboxChannel::class);
Broadcast::channel('conversations.client.{clientId}', \App\Broadcasting\ClientInboxChannel::class);
