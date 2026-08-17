<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra el histórico de WhatsApp que hoy vive dentro de tickets al módulo de
 * Conversaciones.
 *
 * Se hace como comando y no como migración a propósito: hay que poder correrlo,
 * revisar el resultado y volver a correrlo. Es idempotente — se apoya en
 * wa_message_id, que es único, y en la pareja (número, contacto).
 *
 * No borra nada. Los campos viejos de tickets quedan como estaban hasta que se
 * verifique la migración en producción.
 */
class BackfillWhatsAppConversations extends Command
{
    protected $signature = 'whatsapp:backfill
        {--waba= : WABA ID del número existente}
        {--phone-number-id= : Phone Number ID del número existente}
        {--display= : Número en formato legible, ej. +52 1 844 341 0326}
        {--name=LunAvalos : Nombre de la cuenta en la UI}
        {--client= : Client ID dueño del número; omitir si es el propio de LunAvalos}
        {--dry-run : Muestra qué haría sin escribir nada}';

    protected $description = 'Migra los tickets de canal whatsapp al módulo de Conversaciones';

    public function handle(): int
    {
        $waba    = (string) ($this->option('waba') ?: config('services.whatsapp.business_account_id'));
        $phoneId = (string) ($this->option('phone-number-id') ?: config('services.whatsapp.phone_number_id'));

        if ($waba === '' || $phoneId === '') {
            $this->error('Falta --waba o --phone-number-id (y no están en services.whatsapp).');

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');

        $tickets = Ticket::where('channel', Ticket::CHANNEL_WHATSAPP)
            ->whereNotNull('whatsapp_wa_id')
            ->whereNull('conversation_id')
            ->orderBy('id')
            ->get();

        $this->info(sprintf(
            '%s %d ticket(s) de WhatsApp hacia la WABA %s / número %s.',
            $seco ? '[dry-run] Migraría' : 'Migrando',
            $tickets->count(),
            $waba,
            $phoneId,
        ));

        if ($seco) {
            foreach ($tickets as $ticket) {
                $this->line(sprintf(
                    '  ticket #%d  contacto %s  %d mensaje(s)',
                    $ticket->id,
                    $ticket->whatsapp_wa_id,
                    $ticket->messages()->where('channel', Ticket::CHANNEL_WHATSAPP)->count(),
                ));
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tickets, $waba, $phoneId) {
            $numero = $this->asegurarNumero($waba, $phoneId);

            foreach ($tickets as $ticket) {
                $this->migrarTicket($ticket, $numero);
            }
        });

        $this->info('Listo. Los campos viejos de tickets se conservan intactos.');

        return self::SUCCESS;
    }

    private function asegurarNumero(string $waba, string $phoneId): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::firstOrCreate(
            ['waba_id' => $waba],
            ['name' => (string) $this->option('name')],
        );

        return WhatsAppNumber::firstOrCreate(
            ['phone_number_id' => $phoneId],
            [
                'whatsapp_account_id'  => $cuenta->id,
                'client_id'            => $this->option('client') ?: null,
                'display_phone_number' => (string) ($this->option('display') ?: $phoneId),
            ],
        );
    }

    private function migrarTicket(Ticket $ticket, WhatsAppNumber $numero): void
    {
        $conversacion = Conversation::firstOrCreate(
            [
                'whatsapp_number_id' => $numero->id,
                'contact_wa_id'      => $ticket->whatsapp_wa_id,
            ],
            [
                // El client_id del ticket manda sobre el del número: refleja a
                // quién se le atribuyó realmente la conversación en su momento.
                'client_id' => $ticket->client_id ?: $numero->client_id,
                'status'    => Conversation::STATUS_ARCHIVED,
            ],
        );

        $mensajes = $ticket->messages()
            ->where('channel', Ticket::CHANNEL_WHATSAPP)
            ->orderBy('id')
            ->get();

        foreach ($mensajes as $mensaje) {
            $this->migrarMensaje($mensaje, $conversacion);
        }

        $ultimo         = $mensajes->last();
        $ultimoEntrante = $mensajes->where('direction', TicketMessage::DIRECTION_IN)->last();

        $conversacion->forceFill([
            'last_message_at' => $ultimo?->created_at,
            'last_inbound_at' => $ultimoEntrante?->created_at,
        ])->save();

        $ticket->forceFill(['conversation_id' => $conversacion->id])->save();

        $this->line("  ticket #{$ticket->id} → conversación #{$conversacion->id} ({$mensajes->count()} mensajes)");
    }

    private function migrarMensaje(TicketMessage $mensaje, Conversation $conversacion): void
    {
        // Los salientes históricos tienen wa_message_id nulo porque la salida
        // por n8n nunca funcionó: el contacto jamás los recibió. Se migran como
        // fallidos, que es lo que realmente pasó.
        $saliente = $mensaje->direction === TicketMessage::DIRECTION_OUT;

        if ($mensaje->wa_message_id
            && ConversationMessage::where('wa_message_id', $mensaje->wa_message_id)->exists()) {
            return;
        }

        ConversationMessage::create([
            'conversation_id' => $conversacion->id,
            'user_id'         => $mensaje->user_id,
            'author_type'     => $saliente
                ? ConversationMessage::AUTHOR_STAFF
                : ConversationMessage::AUTHOR_CONTACT,
            'direction'       => $saliente
                ? ConversationMessage::DIRECTION_OUT
                : ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => $mensaje->wa_message_id,
            'body'            => $mensaje->message,
            'delivery_status' => $saliente && !$mensaje->wa_message_id
                ? ConversationMessage::DELIVERY_FAILED
                : ConversationMessage::DELIVERY_DELIVERED,
            'delivery_error'  => $saliente && !$mensaje->wa_message_id
                ? 'Nunca se envió: la salida por n8n no estaba operativa.'
                : null,
            'created_at'      => $mensaje->created_at,
            'updated_at'      => $mensaje->updated_at,
        ]);
    }
}
