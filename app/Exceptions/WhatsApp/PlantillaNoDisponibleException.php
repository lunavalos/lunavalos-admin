<?php

namespace App\Exceptions\WhatsApp;

use RuntimeException;

/**
 * La plantilla no existe, no está aprobada, o es de otra WABA.
 *
 * El último caso es el que importa para la seguridad: un id de plantilla en el
 * cuerpo de una petición no puede alcanzar la WABA de otro cliente.
 */
class PlantillaNoDisponibleException extends RuntimeException
{
}
