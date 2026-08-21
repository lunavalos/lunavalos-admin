<?php

namespace App\Services\AI;

use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\WhatsApp\ConversationSender;
use Illuminate\Support\Facades\Log;

/**
 * El agente de IA que contesta una conversación de WhatsApp.
 *
 * Corre aquí y no en n8n por cuatro motivos que quedaron en el plan (Fase 7):
 * un agente por cliente en n8n es un workflow por cliente; el tope de consumo
 * exige contar tokens en una tabla; el contexto del cliente ya vive en esta
 * base y sacarlo por la red en cada mensaje sería exportar datos de clientes
 * finales de terceros; y responderle a un contacto es lo único con reloj.
 *
 * Envía por `ConversationSender`, el mismo camino que la bandeja y la API, así
 * que respeta la ventana de 24 h y guarda el estado de entrega real sin código
 * propio.
 */
class ConversationAgent
{
    /**
     * Cuántos mensajes de historial ve el modelo.
     *
     * Suficiente para entender el hilo, acotado para que el costo por respuesta
     * no crezca con la antigüedad de la conversación — que con un contacto que
     * vuelve durante meses crecería sin fin.
     */
    private const HISTORIAL = 20;

    public function __construct(
        private ConversationSender $sender,
        private ClaudeGateway $claude,
    ) {
    }

    /**
     * Contesta, si toca. Devuelve el mensaje enviado, o null si no respondió.
     *
     * Devolver null no es un fallo: casi siempre significa que el agente
     * decidió correctamente no meterse.
     */
    public function responder(Conversation $conversacion): ?ConversationMessage
    {
        $agente = $this->agenteDe($conversacion);

        if (!$agente?->puedeResponder()) {
            return null;
        }

        $historial = $this->historial($conversacion);

        if ($historial === []) {
            return null;
        }

        try {
            $respuesta = $this->claude->responder(
                $agente,
                $this->promptDelSistema($agente),
                $historial,
            );
        } catch (\Throwable $e) {
            // Un fallo del modelo deja la conversación para el equipo, que es
            // el resultado correcto: sin respuesta automática, no sin
            // respuesta. Por eso no se relanza.
            Log::warning('ia: no se pudo generar la respuesta', [
                'conversacion' => $conversacion->id,
                'agente'       => $agente->id,
                'error'        => $e->getMessage(),
            ]);

            return null;
        }

        // El consumo se registra siempre, incluso si el modelo declinó o no
        // devolvió texto: Anthropic lo cobra igual, y un tope que no cuenta lo
        // gastado no es un tope.
        $this->registrarConsumo($agente, $respuesta);

        if ($respuesta->declinado) {
            Log::info('ia: el modelo declinó responder', [
                'conversacion' => $conversacion->id,
                'categoria'    => $respuesta->categoriaDelRechazo,
            ]);

            return null;
        }

        if ($respuesta->texto === null || trim($respuesta->texto) === '') {
            return null;
        }

        return $this->sender->enviarTexto(
            $conversacion,
            $this->conAviso($agente, $conversacion, trim($respuesta->texto)),
            ConversationMessage::AUTHOR_AI,
        );
    }

    /**
     * El agente del cliente de esta conversación.
     *
     * `client_id` null es el número propio de LunAvalos, y tiene su agente con
     * esa misma clave — no es un caso sin agente.
     */
    private function agenteDe(Conversation $conversacion): ?AiAgent
    {
        return AiAgent::where('client_id', $conversacion->client_id)->first();
    }

    /**
     * Quién es el agente y qué puede hacer.
     *
     * Si el cliente tiene `system_prompt` propio manda ése. Si no, se arma con
     * la ficha comercial que ya está capturada: `briefing_context`,
     * `briefing_target_audience` y `briefing_contact_methods` describen al
     * negocio mejor que un texto escrito de cero, y ya están ahí.
     *
     * No lleva nada variable dentro —ni fecha, ni nombre del contacto— porque
     * viaja como bloque cacheado: un byte distinto invalida la caché.
     */
    public function promptDelSistema(AiAgent $agente): string
    {
        if (filled($agente->system_prompt)) {
            return $agente->system_prompt;
        }

        $cliente = $agente->client;
        $negocio = $cliente?->business_name ?? 'LunAvalos';

        $ficha = collect([
            'A qué se dedica'   => $cliente?->briefing_context,
            'A quién le vende'  => $cliente?->briefing_target_audience,
            'Cómo contactarlos' => $cliente?->briefing_contact_methods,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        return <<<PROMPT
        Atiendes el WhatsApp de {$negocio}. Le escribes a clientes reales de ese
        negocio, en su nombre.

        {$ficha}

        Cómo responder:
        - En español, con el tono de un buen empleado del negocio: cordial y directo.
        - Breve. Es WhatsApp, no un correo. Dos o tres frases suelen bastar.
        - Sin markdown, sin viñetas, sin encabezados. Texto corrido.

        Lo que NO haces:
        - No inventas precios, plazos, disponibilidad, direcciones ni promociones.
          Si no está aquí arriba, no lo sabes.
        - No prometes nada en nombre del negocio: ni descuentos, ni fechas de
          entrega, ni excepciones.
        - No pides datos sensibles: tarjetas, contraseñas, documentos de identidad.

        Cuando no sepas algo, o el contacto pida algo que requiera una decisión
        del negocio, dilo con naturalidad y ofrece pasar con una persona del
        equipo. Quedarte corto es correcto; inventar no.
        PROMPT;
    }

    /**
     * El hilo, en el formato de la API.
     *
     * El contacto es `user` y nosotros somos `assistant` —da igual que un
     * mensaje lo escribiera una persona del equipo o el propio agente: desde el
     * punto de vista del modelo, todo lo que salió de este número es "lo que ya
     * dijimos", y verlo evita que se repita o se contradiga.
     */
    public function historial(Conversation $conversacion): array
    {
        $mensajes = $conversacion->messages()
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderByDesc('id')
            ->limit(self::HISTORIAL)
            ->get()
            ->reverse()
            ->values();

        $historial = [];

        foreach ($mensajes as $mensaje) {
            $rol = $mensaje->direction === ConversationMessage::DIRECTION_IN
                ? 'user'
                : 'assistant';

            // Dos mensajes seguidos del mismo lado se funden en uno: es lo
            // normal en WhatsApp, donde la gente manda tres mensajes cortos en
            // vez de uno largo.
            if ($historial !== [] && end($historial)['role'] === $rol) {
                $historial[array_key_last($historial)]['content'] .= "\n" . $mensaje->body;
                continue;
            }

            $historial[] = ['role' => $rol, 'content' => $mensaje->body];
        }

        // Tiene que empezar por el contacto: la API exige que el primer turno
        // sea `user`, y un hilo que arranca con lo nuestro no es una pregunta.
        while ($historial !== [] && $historial[0]['role'] !== 'user') {
            array_shift($historial);
        }

        // Y terminar en el contacto: si lo último es nuestro, no hay nada nuevo
        // que responder y llamar al modelo sería gastar por gastar.
        if ($historial === [] || end($historial)['role'] !== 'user') {
            return [];
        }

        return $historial;
    }

    /**
     * El primer mensaje del agente en una conversación lleva el aviso de que es
     * automático. Solo el primero: repetirlo en cada respuesta sería ruido, y no
     * avisar nunca no es una opción.
     */
    private function conAviso(AiAgent $agente, Conversation $conversacion, string $respuesta): string
    {
        $yaAviso = $conversacion->messages()
            ->where('author_type', ConversationMessage::AUTHOR_AI)
            ->exists();

        return $yaAviso
            ? $respuesta
            : $agente->avisoDeAutomatizacion() . "\n\n" . $respuesta;
    }

    private function registrarConsumo(AiAgent $agente, ClaudeRespuesta $respuesta): void
    {
        $agente->consumoDelMes()->registrar(
            $respuesta->inputTokens,
            $respuesta->outputTokens,
            $respuesta->cacheReadTokens,
        );
    }
}
