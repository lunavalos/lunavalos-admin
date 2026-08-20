<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * Canal de bandeja del staff interno.
 *
 * Por aquí pasan TODAS las conversaciones: las de todos los clientes y las de
 * los números propios de LunAvalos. Solo lo escucha quien no está amarrado a un
 * cliente, que es la misma condición con la que ConversationController decide
 * si ve la bandeja completa.
 *
 * Va como clase y no como closure para poder probarlo: en los tests el driver
 * de broadcasting es `null`, y ése autoriza cualquier canal sin consultar la
 * regla — un test contra /broadcasting/auth pasaría siempre.
 */
class InternalInboxChannel
{
    public function join(User $user): bool
    {
        return $user->client_id === null;
    }
}
