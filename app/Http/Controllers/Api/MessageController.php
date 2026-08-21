<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsApp\PlantillaNoDisponibleException;
use App\Exceptions\WhatsApp\VentanaCerradaException;
use App\Models\ConversationMessage;
use App\Services\WhatsApp\ConversationSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enviar mensajes desde un sistema externo.
 *
 * Todo pasa por `ConversationSender`, el mismo que usa la bandeja del admin:
 * un mensaje mandado por klwebapp aparece en el hilo, actualiza la bandeja en
 * tiempo real y guarda su estado de entrega igual que uno escrito a mano. Que
 * la API fuera un camino paralelo —con su propia llamada a Graph y sin pasar
 * por la conversación— es justo lo que hace que los equipos acaben con dos
 * historiales que no cuadran.
 */
class MessageController extends ApiController
{
    /**
     * POST /api/v1/mensajes — texto libre.
     *
     * Solo funciona dentro de la ventana de 24 h. Para iniciar una
     * conversación (un lead de landing, un alta en klwebapp) hay que usar
     * `plantilla`: es una regla de Meta, no nuestra.
     */
    public function store(Request $request, ConversationSender $sender): JsonResponse
    {
        $datos = $request->validate([
            'to'              => 'required|string|max:32',
            'body'            => 'required|string|max:4096',
            'client_id'       => 'sometimes|integer',
            'phone_number_id' => 'sometimes|string',
        ]);

        $clienteId = $this->clienteId($request);
        $numero    = $this->numeroDeEnvio($request, $clienteId);
        $waId      = $this->normalizarWaId($datos['to']);

        $conversacion = $sender->resolverConversacion($numero, $waId);

        try {
            $mensaje = $sender->enviarTexto(
                $conversacion,
                $datos['body'],
                ConversationMessage::AUTHOR_STAFF,
            );
        } catch (VentanaCerradaException $e) {
            // 422 y no 500: la petición es válida, el estado no la permite. Y
            // el código deja al llamador reintentar por el camino correcto sin
            // parsear el texto.
            $this->fallar('ventana_cerrada', $e->getMessage(), 422);
        }

        return $this->ok($this->serializar($mensaje, $conversacion->id), 201);
    }

    /**
     * POST /api/v1/mensajes/plantilla — plantilla aprobada.
     *
     * El camino que sirve siempre, dentro y fuera de la ventana. Es el que
     * usan las landings para el primer contacto.
     */
    public function storeTemplate(Request $request, ConversationSender $sender): JsonResponse
    {
        $datos = $request->validate([
            'to'              => 'required|string|max:32',
            'template_id'     => 'required|integer',
            'parametros'      => 'array',
            'parametros.*'    => 'required|string|max:1024',
            'client_id'       => 'sometimes|integer',
            'phone_number_id' => 'sometimes|string',
        ]);

        $clienteId = $this->clienteId($request);
        $numero    = $this->numeroDeEnvio($request, $clienteId);
        $waId      = $this->normalizarWaId($datos['to']);

        $conversacion = $sender->resolverConversacion($numero, $waId);

        try {
            $plantilla = $sender->resolverPlantilla($conversacion, $datos['template_id']);

            $mensaje = $sender->enviarPlantilla(
                $conversacion,
                $plantilla,
                $datos['parametros'] ?? [],
                ConversationMessage::AUTHOR_STAFF,
            );
        } catch (PlantillaNoDisponibleException $e) {
            $this->fallar('plantilla_no_disponible', $e->getMessage(), 422);
        }

        return $this->ok($this->serializar($mensaje, $conversacion->id), 201);
    }

    /**
     * `delivery_status` es lo que de verdad le importa al llamador: un 201 solo
     * dice que lo registramos, no que Meta lo aceptó. Cuando sale `failed`, el
     * mensaje quedó guardado en el hilo pero el contacto no lo recibió.
     */
    private function serializar(ConversationMessage $mensaje, int $conversacionId): array
    {
        return [
            'id'              => $mensaje->id,
            'conversation_id' => $conversacionId,
            'wa_message_id'   => $mensaje->wa_message_id,
            'type'            => $mensaje->type,
            'body'            => $mensaje->body,
            'delivery_status' => $mensaje->delivery_status,
            'delivery_error'  => $mensaje->delivery_error,
            'created_at'      => $mensaje->created_at?->toIso8601String(),
        ];
    }
}
