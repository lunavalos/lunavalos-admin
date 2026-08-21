<?php

namespace App\Services\AI;

use Anthropic\Client;
use App\Models\AiAgent;

/**
 * `ClaudeGateway` contra la API real, con el SDK oficial de PHP.
 *
 * El único archivo del proyecto que conoce el SDK.
 */
class AnthropicGateway implements ClaudeGateway
{
    /**
     * Tope de la respuesta. Un mensaje de WhatsApp largo no se lee; esto es
     * holgado para lo que debe escribir y corta cualquier desbocamiento.
     */
    private const MAX_TOKENS = 2000;

    public function responder(AiAgent $agente, string $promptDelSistema, array $mensajes): ClaudeRespuesta
    {
        // La llave del cliente se pasa explícita para que su agente no acabe
        // usando la nuestra por un descuido de configuración.
        //
        // Cuando no hay ninguna se pasa null a propósito, no cadena vacía: con
        // null el SDK resuelve credenciales por su cuenta (`Client.php:140`) y
        // eso incluye la federación de identidades, donde no existe llave que
        // guardar. Pasar '' rompería ese camino sin dar ningún error.
        $cliente = new Client(apiKey: $agente->llaveApi() ?: null);

        $respuesta = $cliente->messages->create(
            model: $agente->model,
            maxTokens: self::MAX_TOKENS,
            // El prompt va en bloque cacheable: es lo único idéntico en cada
            // mensaje de la conversación, y cachearlo baja el costo por
            // respuesta a una fracción. Por eso no lleva dentro nada variable
            // —ni fecha, ni nombre del contacto—: cualquier byte distinto
            // invalida la caché entera y el ahorro desaparece sin avisar.
            system: [[
                'type'         => 'text',
                'text'         => $promptDelSistema,
                'cacheControl' => ['type' => 'ephemeral'],
            ]],
            // Contestar un WhatsApp no es razonamiento profundo, y un esfuerzo
            // bajo además produce respuestas más cortas — que es exactamente lo
            // que se quiere en este canal.
            outputConfig: ['effort' => 'low'],
            messages: $mensajes,
        );

        $uso = $respuesta->usage ?? null;

        // Un rechazo por seguridad llega como 200 con stopReason 'refusal', no
        // como excepción: hay que mirarlo antes de leer el contenido.
        if ($respuesta->stopReason === 'refusal') {
            return new ClaudeRespuesta(
                texto: null,
                inputTokens: (int) ($uso->inputTokens ?? 0),
                outputTokens: (int) ($uso->outputTokens ?? 0),
                cacheReadTokens: (int) ($uso->cacheReadInputTokens ?? 0),
                declinado: true,
                categoriaDelRechazo: $respuesta->stopDetails?->category,
            );
        }

        $texto = null;

        // El contenido es una lista de bloques polimórficos: con pensamiento
        // activo el primero puede ser un ThinkingBlock, así que hay que buscar
        // el de texto en vez de dar por hecho que es content[0].
        foreach ($respuesta->content as $bloque) {
            if ($bloque->type === 'text') {
                $texto = $bloque->text;
                break;
            }
        }

        return new ClaudeRespuesta(
            texto: $texto,
            inputTokens: (int) ($uso->inputTokens ?? 0),
            outputTokens: (int) ($uso->outputTokens ?? 0),
            cacheReadTokens: (int) ($uso->cacheReadInputTokens ?? 0),
        );
    }
}
