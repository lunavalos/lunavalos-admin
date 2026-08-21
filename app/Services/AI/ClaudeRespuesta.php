<?php

namespace App\Services\AI;

/**
 * Lo que devuelve una llamada al modelo, sin tipos del SDK.
 *
 * Existe para que `ConversationAgent` no dependa de las clases del SDK: así el
 * agente se prueba de verdad —con el texto, los tokens y el caso de rechazo
 * que se quieran— en vez de comprobar solo que se llamó a algo.
 */
readonly class ClaudeRespuesta
{
    public function __construct(
        public ?string $texto,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        /** El modelo declinó por seguridad: hay respuesta HTTP, pero no texto. */
        public bool $declinado = false,
        public ?string $categoriaDelRechazo = null,
    ) {
    }
}
