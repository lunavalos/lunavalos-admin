<?php

namespace App\Jobs;

use App\Exceptions\WhatsApp\PlantillaNoDisponibleException;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\ConversationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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

    /**
     * Tope de avisos por destinatario y hora.
     *
     * Sin esto, una acción masiva en el Kanban —o un comando que toque cien
     * tickets del mismo responsable— dispara cien plantillas seguidas al mismo
     * número. Meta lo lee como ráfaga y lo paga el `quality_rating`, que es
     * compartido por todos los clientes de la WABA.
     */
    private const MAX_POR_HORA = 10;

    public function __construct(
        public int $ticketId,
        public string $cambio,
        public string $quien,
        public int $destinatarioId,
    ) {}

    public function handle(ConversationSender $sender): void
    {
        $nombrePlantilla = config('services.whatsapp.ticket_alerts.template') ?: null;

        if (!$nombrePlantilla) {
            return; // apagado
        }

        $ticket = Ticket::find($this->ticketId);

        if (!$ticket) {
            return; // lo borraron entre el cambio y el aviso
        }

        $destinatario = User::find($this->destinatarioId);
        $destino      = $destinatario?->whatsapp;

        // El caso más común mientras el equipo no tenga sus números cargados.
        // Se loguea con nombre para que se sepa a quién hay que pedírselo, en
        // vez de dejar un id suelto.
        if (!$destino) {
            Log::warning('aviso de ticket: el destinatario no tiene WhatsApp', [
                'ticket_id'    => $this->ticketId,
                'usuario_id'   => $this->destinatarioId,
                'usuario'      => $destinatario?->name,
            ]);

            return;
        }

        if (!$this->dentroDelTope($destino)) {
            Log::warning('aviso de ticket: destinatario por encima del tope horario', [
                'ticket_id'  => $this->ticketId,
                'usuario'    => $destinatario->name,
                'tope'       => self::MAX_POR_HORA,
            ]);

            return;
        }

        $numero = $this->numeroPropio();

        if (!$numero) {
            // Decir contra qué se buscó: el fallo real de este paso fue elegir
            // el número equivocado, y un mensaje sin datos no lo habría dejado
            // ver.
            Log::warning('aviso de ticket: no hay número propio activo', [
                'ticket_id'       => $this->ticketId,
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
                'waba'            => config('services.whatsapp.business_account_id'),
            ]);

            return;
        }

        $plantilla = WhatsAppTemplate::where('whatsapp_account_id', $numero->whatsapp_account_id)
            ->where('name', $nombrePlantilla)
            ->first();

        if (!$plantilla) {
            Log::warning('aviso de ticket: la plantilla configurada no existe', [
                'plantilla' => $nombrePlantilla,
                // El waba_id de Meta además de la FK local: con solo la FK,
                // "waba: 1" no dice de qué cuenta habla y el diagnóstico exige
                // una consulta más.
                'waba_id'   => $numero->account?->waba_id,
                'cuenta'    => $numero->whatsapp_account_id,
                'numero'    => $numero->phone_number_id,
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
     * El tope se cuenta por número, no por usuario ni global: es el número el
     * que Meta ve recibir la ráfaga, y dos usuarios podrían compartirlo.
     *
     * Se consume el intento solo cuando se va a mandar de verdad, para que un
     * aviso descartado por otra razón no gaste cupo.
     */
    private function dentroDelTope(string $destino): bool
    {
        $clave = 'aviso-ticket:' . $destino;

        if (RateLimiter::tooManyAttempts($clave, self::MAX_POR_HORA)) {
            return false;
        }

        RateLimiter::hit($clave, 3600);

        return true;
    }

    /**
     * El número desde el que sale el aviso.
     *
     * `client_id` null significa "nuestro" (§4 del plan), pero **no alcanza
     * para identificarlo**: producción arrastra la WABA de prueba que Meta
     * regala, registrada el 2026-08-19 y nunca borrada, cuyo número también
     * tiene `client_id` null y un id más bajo. Elegir con `first()` cogía ésa,
     * y entonces la plantilla —que vive en la WABA de verdad— "no existía".
     *
     * Quien manda es la configuración, que es la que declara cuál es nuestro
     * número de producción. Primero el `phone_number_id` exacto; si no está
     * puesto, cualquiera de la WABA declarada.
     *
     * **Sin coincidencia no se cae a "cualquier número propio".** Ese fallback
     * era el bug: mandar desde una identidad equivocada es peor que no mandar,
     * y encima es invisible —el número de prueba solo entrega a 5 destinatarios
     * dados de alta a mano, así que el aviso se evapora sin error—.
     */
    private function numeroPropio(): ?WhatsAppNumber
    {
        $propios = fn () => WhatsAppNumber::whereNull('client_id')->where('is_active', true);

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');

        if ($phoneNumberId !== '') {
            $numero = $propios()->where('phone_number_id', $phoneNumberId)->first();

            if ($numero) {
                return $numero;
            }
        }

        $wabaId = (string) config('services.whatsapp.business_account_id');

        if ($wabaId !== '') {
            return $propios()
                ->whereHas('account', fn ($q) => $q->where('waba_id', $wabaId))
                ->orderBy('id')
                ->first();
        }

        return null;
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
