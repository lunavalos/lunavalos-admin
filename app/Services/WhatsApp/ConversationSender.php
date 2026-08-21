<?php

namespace App\Services\WhatsApp;

use App\Events\ConversationMessageSent;
use App\Events\ConversationUpdated;
use App\Exceptions\WhatsApp\PlantillaNoDisponibleException;
use App\Exceptions\WhatsApp\VentanaCerradaException;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;

/**
 * Mandar un mensaje a una conversación: enviarlo, guardarlo, y avisar a la
 * bandeja.
 *
 * Existe porque hay tres caminos que hacen exactamente lo mismo —la UI del
 * admin, la API de plataforma, y el agente de IA— y cada uno tiene que
 * respetar la ventana de 24 h, guardar el estado de entrega real y emitir los
 * dos eventos de tiempo real. Tenerlo tres veces garantiza que un día alguno
 * se quede sin alguna de las tres cosas; el histórico de este módulo ya
 * enseñó que la que se olvida es siempre la del estado de entrega.
 *
 * `WhatsAppService` sigue siendo el que habla con Graph. Esto es la capa de
 * arriba: la que sabe qué es una conversación.
 */
class ConversationSender
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /**
     * Texto libre. Solo funciona dentro de la ventana de 24 h.
     *
     * @throws VentanaCerradaException
     */
    public function enviarTexto(
        Conversation $conversacion,
        string $texto,
        string $autorTipo = ConversationMessage::AUTHOR_STAFF,
        ?int $userId = null,
    ): ConversationMessage {
        if (!$conversacion->ventanaAbierta()) {
            throw new VentanaCerradaException();
        }

        $numero = $conversacion->number;

        $waMessageId = $this->whatsapp->sendText(
            $conversacion->contact_wa_id,
            $texto,
            $numero?->phone_number_id,
            $numero?->tokenParaEnviar(),
        );

        return $this->registrar($conversacion, [
            'user_id'       => $userId,
            'author_type'   => $autorTipo,
            'direction'     => ConversationMessage::DIRECTION_OUT,
            'wa_message_id' => $waMessageId,
            'type'          => 'text',
            'body'          => $texto,
        ], $waMessageId, 'Meta no aceptó el envío. Revisa el log.');
    }

    /**
     * Plantilla aprobada. Es el único camino que funciona con la ventana
     * cerrada, y también sirve dentro de ella — por eso una conversación que
     * nace de un sistema externo (una landing, un alta en klwebapp) tiene que
     * empezar por aquí: sin un entrante previo no hay ventana que valga.
     */
    public function enviarPlantilla(
        Conversation $conversacion,
        WhatsAppTemplate $plantilla,
        array $parametros,
        string $autorTipo = ConversationMessage::AUTHOR_STAFF,
        ?int $userId = null,
    ): ConversationMessage {
        $numero     = $conversacion->number;
        $parametros = array_values($parametros);

        $this->validarPlantilla($conversacion, $plantilla, $parametros);

        $waMessageId = $this->whatsapp->sendTemplate(
            $conversacion->contact_wa_id,
            $plantilla->name,
            $plantilla->language,
            $parametros,
            $numero?->phone_number_id,
            $numero?->tokenParaEnviar(),
        );

        return $this->registrar($conversacion, [
            'user_id'       => $userId,
            'author_type'   => $autorTipo,
            'direction'     => ConversationMessage::DIRECTION_OUT,
            'wa_message_id' => $waMessageId,
            'type'          => 'template',
            // El texto ya sustituido: en el hilo tiene que leerse lo que
            // recibió el contacto, no `pedido_listo`.
            'body'          => $plantilla->previsualizar($parametros),
        ], $waMessageId, 'Meta no aceptó la plantilla. Revisa el log.');
    }

    /**
     * Resuelve la plantilla por id comprobando que sea de la WABA de esta
     * conversación. Un id en el cuerpo de una petición no puede alcanzar la
     * plantilla de otro cliente.
     *
     * @throws PlantillaNoDisponibleException
     */
    public function resolverPlantilla(Conversation $conversacion, int $templateId): WhatsAppTemplate
    {
        $plantilla = WhatsAppTemplate::where('id', $templateId)
            ->where('whatsapp_account_id', $conversacion->number?->whatsapp_account_id)
            ->first();

        if (!$plantilla) {
            throw new PlantillaNoDisponibleException(
                'Esa plantilla no está disponible para esta conversación.',
            );
        }

        return $plantilla;
    }

    /**
     * La conversación de este contacto en este número, creándola si es la
     * primera vez.
     *
     * Es `firstOrCreate` sobre la misma clave única que usa el webhook
     * (§4: un contacto tiene UNA conversación por número), así que un
     * sistema externo que escribe primero y un entrante que llega después
     * aterrizan en el mismo hilo en vez de abrir dos.
     */
    public function resolverConversacion(WhatsAppNumber $numero, string $waId): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'whatsapp_number_id' => $numero->id,
                'contact_wa_id'      => $waId,
            ],
            [
                'client_id' => $numero->client_id,
            ],
        );
    }

    /**
     * @throws PlantillaNoDisponibleException
     */
    private function validarPlantilla(
        Conversation $conversacion,
        WhatsAppTemplate $plantilla,
        array $parametros,
    ): void {
        if ($plantilla->whatsapp_account_id !== $conversacion->number?->whatsapp_account_id) {
            throw new PlantillaNoDisponibleException(
                'Esa plantilla no está disponible para esta conversación.',
            );
        }

        if (!$plantilla->estaAprobada()) {
            throw new PlantillaNoDisponibleException(
                "La plantilla «{$plantilla->name}» no está aprobada por Meta "
                . "(estado: {$plantilla->status}).",
            );
        }

        if (count($parametros) !== $plantilla->body_variables) {
            throw new PlantillaNoDisponibleException(
                "La plantilla necesita exactamente {$plantilla->body_variables} valor(es), "
                . 'y llegaron ' . count($parametros) . '.',
            );
        }
    }

    /**
     * Guardar y avisar. Sin wamid Meta no lo aceptó: se registra como fallido
     * en vez de fingir que salió.
     */
    private function registrar(
        Conversation $conversacion,
        array $atributos,
        ?string $waMessageId,
        string $errorSiFalla,
    ): ConversationMessage {
        $mensaje = $conversacion->messages()->create($atributos + [
            'delivery_status' => $waMessageId
                ? ConversationMessage::DELIVERY_SENT
                : ConversationMessage::DELIVERY_FAILED,
            'delivery_error'  => $waMessageId ? null : $errorSiFalla,
        ]);

        $conversacion->registrarSaliente();

        // `toOthers()` excluye al navegador que originó el envío, que ya pintó
        // el mensaje localmente. Cuando no hay navegador —la API, el agente de
        // IA— no llega la cabecera X-Socket-ID y el evento sale para todos,
        // que es justo lo que hace falta ahí.
        broadcast(new ConversationMessageSent($mensaje))->toOthers();

        // Y el de la lista, sin excluir a nadie: la bandeja del resto del
        // equipo tiene que reordenarse aunque el mensaje lo haya escrito otro.
        broadcast(new ConversationUpdated($conversacion->fresh()));

        return $mensaje;
    }
}
