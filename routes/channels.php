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

