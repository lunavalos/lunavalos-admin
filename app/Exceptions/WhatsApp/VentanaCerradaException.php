<?php

namespace App\Exceptions\WhatsApp;

use RuntimeException;

/**
 * Se intentó mandar texto libre fuera de la ventana de 24 h.
 *
 * Es una excepción y no un `return null` a propósito: §8 del plan describe
 * exactamente el fallo que produce tragarse esto —el mensaje se guarda, Meta
 * responde 131047, y el contacto nunca lo recibe sin que nadie se entere—.
 * Quien envía tiene que decidir qué hacer, no seguir como si nada.
 */
class VentanaCerradaException extends RuntimeException
{
    public function __construct(string $mensaje = '')
    {
        parent::__construct($mensaje ?: 'La ventana de 24 horas está cerrada. '
            . 'Meta no entrega texto libre; hace falta una plantilla aprobada.');
    }
}
