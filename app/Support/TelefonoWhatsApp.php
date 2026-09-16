<?php

namespace App\Support;

/**
 * Convierte un teléfono escrito por una persona en el `wa_id` que entiende Meta.
 *
 * Existe porque el formato es el error silencioso más caro de este módulo:
 * Meta identifica al contacto por dígitos con lada de país, y un `8442751165`
 * —como se escribe un teléfono en Saltillo— abre una conversación con un
 * destinatario que no existe. No falla: se manda, se registra, y nadie lo
 * recibe.
 *
 * Por eso la normalización vive aquí y no en cada formulario.
 */
final class TelefonoWhatsApp
{
    /** Los 10 dígitos nacionales sin lada son la forma en que se escribe aquí. */
    private const LADA_MEXICO = '52';

    /** Un wa_id por debajo de esto no es un teléfono, es un dedazo. */
    private const MINIMO_DIGITOS = 11;

    private const MAXIMO_DIGITOS = 15;

    /**
     * Devuelve el wa_id, o null si lo que llegó no puede serlo.
     *
     * Null y no una excepción: esto corre al guardar un perfil, y un teléfono
     * mal escrito no debe impedir que alguien cambie su nombre. El campo vuelve
     * vacío al formulario, que es la señal de que no se aceptó.
     */
    public static function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono) ?? '';

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 10) {
            $digitos = self::LADA_MEXICO . $digitos;
        }

        $largo = strlen($digitos);

        return $largo >= self::MINIMO_DIGITOS && $largo <= self::MAXIMO_DIGITOS
            ? $digitos
            : null;
    }
}
