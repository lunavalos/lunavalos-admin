<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AI\ConversationAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deja que el agente conteste un entrante.
 *
 * En cola porque cuelga del webhook de Meta, que exige 200 rápido: una llamada
 * al modelo tarda segundos, y hacerla dentro del request provocaría reintentos
 * de Meta y acabaría degradando la suscripción.
 *
 * Un solo intento a propósito. Un reintento llegaría tarde —el contacto ya
 * estaría esperando, o un humano ya habría entrado— y arriesgaría mandarle dos
 * respuestas al contacto por un fallo posterior al envío. Sin respuesta
 * automática es un resultado aceptable; duplicada no.
 */
class ResponderConIA implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public Conversation $conversacion)
    {
    }

    public function handle(ConversationAgent $agente): void
    {
        // Se vuelve a comprobar aquí, no solo al despachar: entre que el
        // webhook encoló y el worker lo tomó pueden pasar segundos, y en esos
        // segundos alguien del equipo pudo abrir la conversación y tomarla.
        // Esa carrera es justo la que hace que un bot conteste encima de una
        // persona.
        if (!$this->conversacion->fresh()?->debeResponderIa()) {
            return;
        }

        $agente->responder($this->conversacion->fresh());
    }
}
