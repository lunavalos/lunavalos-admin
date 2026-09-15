<?php

namespace App\Jobs;

use App\Exceptions\WhatsApp\PlantillaNoDisponibleException;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\ConversationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Aviso interno por WhatsApp cuando cambia un ticket.
 *
 * Va en cola por la razón de siempre en este módulo: un fallo al avisar no
 * puede tumbar la petición que movió el ticket. El staff arrastra una tarjeta
 * del Kanban y eso tiene que guardarse aunque Meta esté caído.
 *
 * **Siempre plantilla, nunca texto libre.** Un aviso lo iniciamos nosotros, y
 * casi siempre fuera de la ventana de 24 h —nadie le escribe a su propio
 * sistema de tickets—, así que `sendText` devolvería 131047. Es la regla del
 * §8 del plan aplicada al primer caso real que la necesita.
 */
class NotifyTicketUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Dos reintentos y fuera. Si falla es casi siempre por configuración
     * —plantilla sin aprobar, destino mal escrito— y reintentar veinte veces
     * solo llena la tabla de jobs fallidos con el mismo error.
     */
    public int $tries = 2;

    public function __construct(
        public int $ticketId,
        public string $cambio,
        public string $quien,
    ) {}

    public function handle(ConversationSender $sender): void
    {
        [$destino, $nombrePlantilla] = $this->configuracion();

        if (!$destino || !$nombrePlantilla) {
            return; // apagado
        }

        $ticket = Ticket::find($this->ticketId);

        if (!$ticket) {
            return; // lo borraron entre el cambio y el aviso
        }

        $numero = $this->numeroPropio();

        if (!$numero) {
            Log::warning('aviso de ticket: no hay número propio activo', [
                'ticket_id' => $this->ticketId,
            ]);

            return;
        }

        $plantilla = WhatsAppTemplate::where('whatsapp_account_id', $numero->whatsapp_account_id)
            ->where('name', $nombrePlantilla)
            ->first();

        if (!$plantilla) {
            Log::warning('aviso de ticket: la plantilla configurada no existe', [
                'plantilla' => $nombrePlantilla,
                'waba'      => $numero->whatsapp_account_id,
            ]);

            return;
        }

        $conversacion = $sender->resolverConversacion($numero, $destino);

        try {
            $sender->enviarPlantilla(
                $conversacion,
                $plantilla,
                [
                    $this->limpiar('#' . $ticket->id),
                    $this->limpiar($ticket->title ?? 'sin título'),
                    $this->limpiar($this->cambio),
                    $this->limpiar($this->quien),
                ],
                ConversationMessage::AUTHOR_SYSTEM,
            );
        } catch (PlantillaNoDisponibleException $e) {
            // Reintentar no la va a aprobar ni a cambiarle las variables.
            Log::warning('aviso de ticket: ' . $e->getMessage(), [
                'ticket_id' => $this->ticketId,
                'plantilla' => $nombrePlantilla,
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function configuracion(): array
    {
        $destino = preg_replace('/\D+/', '', (string) config('services.whatsapp.ticket_alerts.to'));

        return [
            $destino ?: null,
            config('services.whatsapp.ticket_alerts.template') ?: null,
        ];
    }

    /**
     * El número propio de LunAvalos es el que tiene `client_id` null (§4 del
     * plan). Si hubiera más de uno activo se toma el primero: son todos
     * nuestros, y aquí el emisor da igual mientras el aviso llegue.
     */
    private function numeroPropio(): ?WhatsAppNumber
    {
        return WhatsAppNumber::whereNull('client_id')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Meta rechaza los parámetros de plantilla que traen saltos de línea,
     * tabuladores o cuatro espacios seguidos. Un título de ticket pegado desde
     * un correo trae las tres cosas, así que sin esto el aviso fallaría con un
     * 132000 que no explica nada.
     */
    private function limpiar(string $valor): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return mb_substr($limpio !== '' ? $limpio : '—', 0, 200);
    }
}
