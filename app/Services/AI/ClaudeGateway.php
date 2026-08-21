<?php

namespace App\Services\AI;

use App\Models\AiAgent;

/**
 * La frontera con la API de Claude.
 *
 * Todo el uso del SDK vive detrás de esto. Dos motivos: los tests pueden
 * sustituirlo por un doble sin levantar red —el SDK trae su propio cliente
 * HTTP, así que `Http::fake()` no lo alcanza—, y el día que cambie una firma
 * del SDK hay un solo archivo que tocar.
 */
interface ClaudeGateway
{
    /**
     * @param array<int, array{role: string, content: string}> $mensajes
     */
    public function responder(AiAgent $agente, string $promptDelSistema, array $mensajes): ClaudeRespuesta;
}
