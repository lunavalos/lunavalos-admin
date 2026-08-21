<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiConsumer;
use App\Models\WhatsAppNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base de /api/v1: resolver sobre QUÉ cliente actúa esta petición, y con qué
 * número, sin que un consumidor pueda salirse de lo suyo.
 *
 * Es el equivalente de `ConversationController::autorizar()` para máquinas.
 * Allí el alcance sale de `users.client_id`; aquí de `api_consumers.client_id`.
 */
abstract class ApiController extends Controller
{
    protected function consumidor(Request $request): ApiConsumer
    {
        /** @var ApiConsumer */
        return $request->user();
    }

    /**
     * El cliente sobre el que actúa esta petición.
     *
     * Un consumidor atado ignora lo que venga en el cuerpo: su cliente es el
     * suyo y punto. Uno interno tiene que nombrarlo, y se comprueba igual —
     * "interno" significa que puede elegir, no que se le crea sin más.
     *
     * Devuelve null solo para el número propio de LunAvalos (client_id null),
     * que es un caso legítimo: es nuestro propio número.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function clienteId(Request $request): ?int
    {
        $consumidor = $this->consumidor($request);

        if ($consumidor->estaAtado()) {
            return $consumidor->client_id;
        }

        // Interno: puede operar sobre cualquiera, pero tiene que decir cuál.
        $solicitado = $request->input('client_id');

        if ($solicitado === null) {
            $this->fallar(
                'cliente_requerido',
                'Esta integración opera sobre varios clientes: indica `client_id`.',
                422,
            );
        }

        return (int) $solicitado;
    }

    /**
     * El número desde el que sale el mensaje.
     *
     * Si la petición nombra un `phone_number_id`, se usa ése —comprobando que
     * sea del cliente resuelto—. Si no, el único número activo del cliente.
     *
     * Con varios números activos y ninguno indicado se falla en vez de elegir:
     * el número determina desde qué identidad ve el contacto el mensaje, y
     * adivinarlo manda el mensaje desde la marca equivocada.
     */
    protected function numeroDeEnvio(Request $request, ?int $clienteId): WhatsAppNumber
    {
        $consulta = WhatsAppNumber::where('is_active', true)
            ->where('client_id', $clienteId);

        if ($solicitado = $request->input('phone_number_id')) {
            $numero = (clone $consulta)->where('phone_number_id', $solicitado)->first();

            if (!$numero) {
                // Mismo mensaje exista o no exista el número en otro cliente:
                // "no es tuyo" y "no existe" no deben distinguirse desde fuera.
                $this->fallar(
                    'numero_no_encontrado',
                    "No hay un número activo `{$solicitado}` disponible para esta integración.",
                    404,
                );
            }

            return $numero;
        }

        $numeros = $consulta->get();

        if ($numeros->isEmpty()) {
            $this->fallar(
                'numero_no_encontrado',
                'Este cliente no tiene ningún número de WhatsApp activo.',
                404,
            );
        }

        if ($numeros->count() > 1) {
            $this->fallar(
                'numero_ambiguo',
                'Este cliente tiene varios números activos: indica `phone_number_id`. '
                    . 'Disponibles: ' . $numeros->pluck('phone_number_id')->join(', '),
                422,
            );
        }

        return $numeros->first();
    }

    /**
     * Normaliza un teléfono a wa_id.
     *
     * Meta identifica al contacto por dígitos, sin `+` ni separadores, y una
     * landing manda "+52 844 341 0326" tal cual lo escribió la persona. Sin
     * esto, el mismo contacto abriría una conversación distinta por cada
     * formato que llegue.
     */
    protected function normalizarWaId(string $telefono): string
    {
        return preg_replace('/\D+/', '', $telefono) ?? '';
    }

    /**
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function fallar(string $codigo, string $mensaje, int $estado): never
    {
        abort(response()->json([
            'error' => ['code' => $codigo, 'message' => $mensaje],
        ], $estado));
    }

    protected function ok(array $datos, int $estado = 200): JsonResponse
    {
        return response()->json($datos, $estado);
    }
}
