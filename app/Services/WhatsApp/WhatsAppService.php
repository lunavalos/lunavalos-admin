<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Envía un mensaje de texto vía WhatsApp Cloud API.
     * Devuelve el wa_message_id (wamid) que Meta asigna al mensaje, o null si falló.
     */
    public function sendText(string $to, string $message): ?string
    {
        $version       = config('services.whatsapp.api_version', 'v21.0');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token         = config('services.whatsapp.token');

        try {
            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ])
                ->throw()
                ->json();

            return $response['messages'][0]['id'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: fallo al enviar mensaje', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Marca un mensaje entrante como leído (doble check azul).
     */
    public function markAsRead(string $waMessageId): void
    {
        $version       = config('services.whatsapp.api_version', 'v21.0');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token         = config('services.whatsapp.token');

        try {
            Http::withToken($token)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status'            => 'read',
                    'message_id'        => $waMessageId,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: fallo al marcar mensaje como leído', [
                'wa_message_id' => $waMessageId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
