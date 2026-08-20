<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * Canal de bandeja de un cliente concreto.
 *
 * Aquí viajan el nombre y el teléfono de los clientes finales de ese cliente,
 * así que un usuario de portal solo puede escuchar el suyo. El staff interno
 * pasa porque de todos modos lo ve todo por el canal interno.
 */
class ClientInboxChannel
{
    public function join(User $user, int|string $clientId): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $clientId;
    }
}
