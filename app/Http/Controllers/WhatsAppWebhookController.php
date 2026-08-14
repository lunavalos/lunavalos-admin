<?php

namespace App\Http\Controllers;

use App\Events\TicketMessageSent;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handshake de suscripción. Meta lo hace por GET al guardar el Callback URL
     * y cada vez que reactiva la suscripción.
     *
     * Dos detalles que rompen la verificación si se pasan por alto:
     *   1. PHP convierte los puntos de la query en guiones bajos, así que los
     *      parámetros llegan como `hub_challenge`, no `hub.challenge`.
     *   2. El challenge se devuelve TAL CUAL, como texto plano. Si se responde
     *      JSON, Meta rechaza la suscripción.
     */
    public function verify(Request $request)
    {
        $esperado = (string) config('services.whatsapp.verify_token');

        $params    = $request->query();
        $recibido  = (string) ($params['hub_verify_token'] ?? $params['hub.verify_token'] ?? '');
        $challenge = (string) ($params['hub_challenge']    ?? $params['hub.challenge']    ?? '');

        if ($esperado === '' || !hash_equals($esperado, $recibido)) {
            Log::warning('meta: handshake rechazado', [
                'ip'         => $request->ip(),
                'sin_config' => $esperado === '',
            ]);

            return response('Forbidden', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Eventos entrantes de Meta, autenticados por VerifyMetaSignature.
     *
     * Siempre respondemos 200 rápido — si Meta recibe error, reintenta y
     * termina degradando la suscripción.
     */
    public function receive(Request $request, WhatsAppService $whatsapp)
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleIncomingMessage($message, $value['contacts'] ?? [], $whatsapp);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleIncomingMessage(array $message, array $contacts, WhatsAppService $whatsapp): void
    {
        $waMessageId = $message['id'] ?? null;
        $waId        = $message['from'] ?? null;

        if (!$waMessageId || !$waId) {
            return;
        }

        // El webhook puede reintentar el mismo evento — evitamos duplicar el mensaje.
        if (TicketMessage::where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $text = $message['text']['body']
            ?? $message['button']['text'] ?? null
            ?? $message['interactive']['button_reply']['title'] ?? null
            ?? $message['interactive']['list_reply']['title'] ?? null;

        if ($text === null) {
            $text = '[Mensaje de WhatsApp sin texto — tipo: ' . ($message['type'] ?? 'desconocido') . ']';
        }

        $contactName = collect($contacts)->firstWhere('wa_id', $waId)['profile']['name'] ?? null;
        $client      = $this->findClientByPhone($waId);

        $ticket = Ticket::query()->openWhatsappConversation($waId)->latest('id')->first();
        $isNewTicket = !$ticket;

        if (!$ticket) {
            $ticket = Ticket::create([
                'title'             => 'WhatsApp · ' . ($client?->business_name ?: $contactName ?: $waId),
                'content'           => $text,
                'priority'          => 'Media',
                'status'            => 'Nuevos',
                'source_type'       => Ticket::SOURCE_SUPPORT,
                'channel'           => Ticket::CHANNEL_WHATSAPP,
                'whatsapp_wa_id'    => $waId,
                'creator_id'        => $this->systemUser()->id,
                'client_id'         => $client?->id,
                'visible_to_client' => (bool) $client,
            ]);
        }

        $newMsg = $ticket->messages()->create([
            'user_id'       => null,
            'message'       => $text,
            'channel'       => Ticket::CHANNEL_WHATSAPP,
            'direction'     => TicketMessage::DIRECTION_IN,
            'wa_message_id' => $waMessageId,
        ]);
        broadcast(new TicketMessageSent($newMsg))->toOthers();

        $whatsapp->markAsRead($waMessageId);

        // Acuse de recibo solo al abrir el ticket — para no saturar la conversación
        // en cada mensaje de seguimiento del cliente.
        if ($isNewTicket) {
            $ack = "¡Hola! 👋 Recibimos tu mensaje y abrimos el ticket #{$ticket->id} para darle seguimiento. "
                . 'En breve alguien de nuestro equipo te atenderá por este mismo chat.';

            $ackWaId = $whatsapp->sendText($waId, $ack);

            $ackMsg = $ticket->messages()->create([
                'user_id'       => null,
                'message'       => $ack,
                'channel'       => Ticket::CHANNEL_WHATSAPP,
                'direction'     => TicketMessage::DIRECTION_OUT,
                'wa_message_id' => $ackWaId,
            ]);
            broadcast(new TicketMessageSent($ackMsg))->toOthers();
        }
    }

    /**
     * Empareja el wa_id entrante con un Client existente comparando solo dígitos
     * y los últimos 10 (longitud de un número local en MX), ya que el formato
     * guardado en clients.phone no siempre incluye lada/código de país.
     */
    private function findClientByPhone(string $waId): ?Client
    {
        $waDigits = preg_replace('/\D+/', '', $waId);
        if (strlen($waDigits) < 8) {
            return null;
        }
        $suffix = substr($waDigits, -10);

        return Client::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'business_name'])
            ->first(fn (Client $client) => str_ends_with(preg_replace('/\D+/', '', (string) $client->phone), $suffix));
    }

    /**
     * Usuario "robot" usado como creator_id de los tickets que abre el webhook
     * (creator_id es obligatorio en la tabla tickets).
     */
    private function systemUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'whatsapp-bot@lunavalos.local'],
            [
                'name'     => 'WhatsApp Bot',
                'password' => Hash::make(Str::random(40)),
            ]
        );
    }
}
