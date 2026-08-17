<?php

namespace App\Http\Controllers;

use App\Jobs\MarkWhatsAppMessageRead;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handshake de suscripción. Meta manda hub.mode / hub.verify_token /
     * hub.challenge y espera el challenge de vuelta TAL CUAL, en texto plano:
     * envuelto en JSON, rechaza la suscripción.
     *
     * PHP convierte los puntos de la query en guiones bajos, así que los
     * parámetros llegan como hub_challenge, no hub.challenge.
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
     * Un solo endpoint para TODOS los clientes: se enruta por entry[].id (la
     * WABA) y por value.metadata.phone_number_id (el número). El cliente se
     * deduce del número, que es determinista, en vez de adivinarse comparando
     * sufijos de teléfono como antes.
     *
     * Siempre respondemos 200 rápido — si Meta recibe error, reintenta y
     * termina degradando la suscripción. Por eso aquí no se llama a Graph:
     * lo que necesita red se despacha a la cola.
     */
    public function receive(Request $request)
    {
        foreach ($request->input('entry', []) as $entry) {
            $account = WhatsAppAccount::where('waba_id', $entry['id'] ?? '')->first();

            if (!$account) {
                // WABA que no administramos (o que ya se desconectó). No es un
                // error nuestro, pero conviene verlo si empieza a repetirse.
                Log::info('whatsapp: evento de una WABA desconocida', ['waba_id' => $entry['id'] ?? null]);
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $value  = $change['value'] ?? [];
                $numero = $this->resolverNumero($value);

                if (!$numero) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $mensaje) {
                    $this->procesarEntrante($mensaje, $value['contacts'] ?? [], $numero);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->procesarEstado($status);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function resolverNumero(array $value): ?WhatsAppNumber
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            return null;
        }

        return WhatsAppNumber::with('account')
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }

    private function procesarEntrante(array $mensaje, array $contactos, WhatsAppNumber $numero): void
    {
        $waMessageId = $mensaje['id']   ?? null;
        $waId        = $mensaje['from'] ?? null;

        if (!$waMessageId || !$waId) {
            return;
        }

        // Meta reintenta el mismo evento — evitamos duplicar el mensaje.
        if (ConversationMessage::where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $nombre = collect($contactos)->firstWhere('wa_id', $waId)['profile']['name'] ?? null;

        $conversacion = Conversation::firstOrCreate(
            [
                'whatsapp_number_id' => $numero->id,
                'contact_wa_id'      => $waId,
            ],
            [
                'client_id'    => $numero->client_id,
                'contact_name' => $nombre,
            ],
        );

        $conversacion->messages()->create([
            'user_id'         => null,
            'author_type'     => ConversationMessage::AUTHOR_CONTACT,
            'direction'       => ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => $waMessageId,
            'type'            => $mensaje['type'] ?? 'text',
            'body'            => $this->extraerTexto($mensaje),
            // Un entrante ya está entregado por definición: el estado de
            // entrega solo describe lo que nosotros mandamos.
            'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
        ]);

        $conversacion->registrarEntrante($nombre);

        MarkWhatsAppMessageRead::dispatch(
            $waMessageId,
            $numero->phone_number_id,
            $numero->tokenParaEnviar(),
        );
    }

    /**
     * Los eventos de `statuses` son los que cierran el agujero de los envíos
     * fallidos invisibles: sin ellos, un mensaje rechazado por Meta quedaba
     * guardado como si hubiera llegado.
     */
    private function procesarEstado(array $status): void
    {
        $waMessageId = $status['id']     ?? null;
        $estado      = $status['status'] ?? null;

        if (!$waMessageId || !$estado) {
            return;
        }

        $mensaje = ConversationMessage::where('wa_message_id', $waMessageId)->first();

        if (!$mensaje) {
            return;
        }

        $permitidos = [
            ConversationMessage::DELIVERY_SENT,
            ConversationMessage::DELIVERY_DELIVERED,
            ConversationMessage::DELIVERY_READ,
            ConversationMessage::DELIVERY_FAILED,
        ];

        if (!in_array($estado, $permitidos, true)) {
            return;
        }

        $mensaje->update([
            'delivery_status' => $estado,
            'delivery_error'  => $status['errors'][0]['title'] ?? null,
        ]);
    }

    /**
     * WhatsApp manda el texto en un sitio distinto según el tipo de mensaje.
     * Lo que no es texto se registra con una marca legible para que la
     * conversación no muestre un hueco.
     */
    private function extraerTexto(array $mensaje): string
    {
        $texto = $mensaje['text']['body']
            ?? $mensaje['button']['text']
            ?? $mensaje['interactive']['button_reply']['title']
            ?? $mensaje['interactive']['list_reply']['title']
            ?? $mensaje['image']['caption']
            ?? $mensaje['document']['caption']
            ?? null;

        return $texto ?? '[Mensaje sin texto — tipo: ' . ($mensaje['type'] ?? 'desconocido') . ']';
    }
}
