<?php

namespace App\Jobs;

use App\Models\ApiConsumer;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Entrega un evento a los sistemas externos suscritos.
 *
 * Es la otra mitad de la API: sin esto klwebapp podría mandar mensajes pero
 * nunca enterarse de las respuestas, y acabaría haciendo polling —o peor,
 * pidiendo un segundo Callback URL en Meta, que es exactamente el fallo que
 * costó una semana cuando había dos apps apuntando al mismo endpoint.
 *
 * Va en cola porque cuelga del webhook de Meta, que exige 200 rápido.
 *
 * **A quién se entrega.** Se usa la misma regla que para enviar
 * (`puedeOperarSobre`): un consumidor atado a un cliente recibe solo lo de su
 * cliente; uno interno de LunAvalos recibe todo. La asimetría sería peor: un
 * sistema que puede escribirle a cualquier cliente y no puede leer lo que le
 * contestan no serviría para nada.
 */
class NotifyApiConsumers implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Un endpoint caído no debe perder el evento, pero tampoco reintentarse
     * eternamente: tres intentos separados, y al log si no.
     */
    public int $tries = 3;

    /** @return array<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(
        public Conversation $conversacion,
        public ConversationMessage $mensaje,
        public string $tipo = 'mensaje.entrante',
    ) {
    }

    public function handle(): void
    {
        $destinatarios = ApiConsumer::query()
            ->where('status', ApiConsumer::STATUS_ACTIVE)
            ->whereNotNull('webhook_url')
            ->whereNotNull('webhook_secret')
            ->get()
            ->filter(fn (ApiConsumer $c) => $c->puedeOperarSobre($this->conversacion->client_id));

        foreach ($destinatarios as $consumidor) {
            $this->entregar($consumidor);
        }
    }

    private function entregar(ApiConsumer $consumidor): void
    {
        // El cuerpo se serializa UNA vez y se firma ese mismo texto: firmar el
        // array y mandar otra serialización es cómo se rompen las firmas.
        $cuerpo = json_encode($this->carga(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $firma = 'sha256=' . hash_hmac('sha256', $cuerpo, $consumidor->webhook_secret);

        try {
            $respuesta = Http::timeout(10)
                ->withBody($cuerpo, 'application/json')
                ->withHeaders([
                    // Mismo formato que usa Meta con nosotros: quien ya integró
                    // el webhook de WhatsApp reconoce el patrón.
                    'X-LunAvalos-Signature' => $firma,
                    'X-LunAvalos-Event'     => $this->tipo,
                ])
                ->post($consumidor->webhook_url);

            if ($respuesta->failed()) {
                Log::warning('api: el consumidor rechazó la entrega', [
                    'consumidor' => $consumidor->slug,
                    'estado'     => $respuesta->status(),
                    'evento'     => $this->tipo,
                ]);
            }
        } catch (\Throwable $e) {
            // No se relanza: un endpoint caído de un consumidor no puede
            // arrastrar la entrega de los demás ni marcar el job como fallido
            // para todos.
            Log::warning('api: no se pudo entregar al consumidor', [
                'consumidor' => $consumidor->slug,
                'error'      => $e->getMessage(),
                'evento'     => $this->tipo,
            ]);
        }
    }

    private function carga(): array
    {
        return [
            'evento'       => $this->tipo,
            'ocurrido_el'  => $this->mensaje->created_at?->toIso8601String(),
            'conversacion' => [
                'id'              => $this->conversacion->id,
                'client_id'       => $this->conversacion->client_id,
                'contact_wa_id'   => $this->conversacion->contact_wa_id,
                'contact_name'    => $this->conversacion->contact_name,
                // Para que el receptor sepa de entrada si puede contestar con
                // texto libre o necesita plantilla.
                'ventana_abierta' => $this->conversacion->ventanaAbierta(),
            ],
            'mensaje' => [
                'id'            => $this->mensaje->id,
                'wa_message_id' => $this->mensaje->wa_message_id,
                'author_type'   => $this->mensaje->author_type,
                'direction'     => $this->mensaje->direction,
                'type'          => $this->mensaje->type,
                'body'          => $this->mensaje->body,
            ],
        ];
    }
}
