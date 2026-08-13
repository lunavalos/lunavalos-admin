<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del workflow de n8n que opera la WhatsApp Cloud API.
 *
 * Este sistema nunca llama a graph.facebook.com: el token de Meta vive
 * únicamente en las credenciales de n8n. Cada sistema tiene su propia URL
 * de webhook en n8n, y es esa URL la que determina desde qué número sale
 * el mensaje — por eso aquí no viajan ni el phone_number_id ni el token.
 */
class WhatsAppService
{
    /**
     * Envía un mensaje de texto. Devuelve el wamid que Meta asignó
     * (reenviado por n8n), o null si falló.
     */
    public function sendText(string $to, string $message): ?string
    {
        $response = $this->call('send_text', [
            'to'   => $to,
            'text' => $message,
        ]);

        return $response['wa_message_id'] ?? null;
    }

    /**
     * Marca un mensaje entrante como leído (doble check azul).
     */
    public function markAsRead(string $waMessageId): void
    {
        $this->call('mark_read', ['wa_message_id' => $waMessageId]);
    }

    /**
     * Un fallo aquí nunca debe tumbar la petición que lo originó: el ticket y
     * su mensaje ya se guardaron, y perder el envío a WhatsApp es preferible a
     * perder el registro. Por eso se loguea y se devuelve null.
     */
    private function call(string $action, array $payload): ?array
    {
        $url    = config('services.n8n.whatsapp_webhook_url');
        $secret = config('services.n8n.shared_secret');

        if (!$url || !$secret) {
            Log::warning('n8n: envío omitido, falta configuración', ['action' => $action]);

            return null;
        }

        try {
            return Http::withHeaders(['X-N8n-Secret' => $secret])
                ->timeout((int) config('services.n8n.timeout', 10))
                ->retry(2, 200, throw: false)
                ->post($url, array_merge(['action' => $action], $payload))
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('n8n: fallo al operar WhatsApp', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }
}
