<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la WhatsApp Cloud API (graph.facebook.com).
 *
 * Antes la salida iba por un workflow de n8n que nunca llegó a existir, así que
 * ningún envío funcionó jamás: todos los mensajes salientes quedaron guardados
 * con wa_message_id nulo. Ahora este sistema habla directo con Meta.
 *
 * El número y el token son parámetros opcionales para que, cuando exista el
 * esquema multi-WABA, cada cliente pueda enviar desde el suyo sin tocar a los
 * llamadores: si no se pasan, se usan los de configuración.
 */
class WhatsAppService
{
    /**
     * Envía un mensaje de texto. Devuelve el wamid que asignó Meta, o null si
     * el envío falló.
     */
    public function sendText(
        string $to,
        string $message,
        ?string $phoneNumberId = null,
        ?string $token = null,
    ): ?string {
        $respuesta = $this->call([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ], 'send_text', $phoneNumberId, $token);

        return $respuesta['messages'][0]['id'] ?? null;
    }

    /**
     * Marca un mensaje entrante como leído (doble check azul).
     */
    public function markAsRead(
        string $waMessageId,
        ?string $phoneNumberId = null,
        ?string $token = null,
    ): void {
        $this->call([
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $waMessageId,
        ], 'mark_read', $phoneNumberId, $token);
    }

    /**
     * Un fallo aquí nunca debe tumbar la petición que lo originó: el ticket y
     * su mensaje ya se guardaron, y perder el envío es preferible a perder el
     * registro. Por eso se loguea y se devuelve null.
     */
    private function call(
        array $payload,
        string $accion,
        ?string $phoneNumberId,
        ?string $token,
    ): ?array {
        $phoneNumberId ??= (string) config('services.whatsapp.phone_number_id');
        $token         ??= (string) config('services.whatsapp.token');
        $version         = (string) config('services.whatsapp.graph_version', 'v26.0');

        if ($phoneNumberId === '' || $token === '') {
            Log::warning('whatsapp: envío omitido, falta configuración', [
                'accion'        => $accion,
                'sin_numero'    => $phoneNumberId === '',
                'sin_token'     => $token === '',
            ]);

            return null;
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        try {
            $respuesta = Http::withToken($token)
                ->timeout((int) config('services.whatsapp.timeout', 10))
                // Solo se reintenta lo que puede prosperar en un segundo intento.
                // Un 4xx vuelve a fallar igual, y reintentar un envío que quizá
                // sí llegó duplicaría el mensaje del cliente.
                ->retry(2, 250, function (\Throwable $e) {
                    return $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError());
                }, throw: false)
                ->post($url, $payload);

            if ($respuesta->failed()) {
                $error = $respuesta->json('error', []);

                Log::warning('whatsapp: Meta rechazó la operación', [
                    'accion'          => $accion,
                    'http'            => $respuesta->status(),
                    'codigo'          => $error['code']         ?? null,
                    'subcodigo'       => $error['error_subcode'] ?? null,
                    'mensaje'         => $error['message']      ?? null,
                    'fbtrace_id'      => $error['fbtrace_id']   ?? null,
                    // 131047: fuera de la ventana de 24 h. Hace falta plantilla
                    // aprobada; el texto libre no se entrega.
                    'fuera_ventana'   => ($error['code'] ?? null) === 131047,
                ]);

                return null;
            }

            return $respuesta->json();
        } catch (\Throwable $e) {
            Log::warning('whatsapp: fallo al operar', [
                'accion' => $accion,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }
}
