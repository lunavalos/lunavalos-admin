<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiUsage;
use App\Models\Client;
use App\Services\AI\ConversationAgent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

/**
 * Los agentes de IA: uno por cliente.
 *
 * Esta pantalla es la razón por la que el agente vive en Laravel y no en n8n.
 * El único argumento real a favor de n8n era poder afinar el prompt sin
 * desplegar; sin una pantalla, ese argumento seguía en pie y el cuello de
 * botella era el equipo de desarrollo.
 *
 * Va cerrada por permiso propio y no por el de Conversaciones: cambiar el
 * prompt de un agente es decidir qué le dice el negocio a sus clientes en
 * automático, que es más grave que responder un mensaje a mano.
 */
class AiAgentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:Gestionar Agentes IA')];
    }

    /**
     * Un usuario amarrado a un cliente solo ve su agente. Mismo criterio que
     * el resto del módulo — y aquí importa el doble, porque el prompt de un
     * cliente describe su negocio.
     */
    private function autorizar(Request $request, ?int $clientId): void
    {
        $propio = $request->user()?->client_id;

        abort_if($propio !== null && $propio !== $clientId, 403, 'Acceso denegado.');
    }

    public function index(Request $request)
    {
        $propio = $request->user()?->client_id;

        $agentes = AiAgent::with('client:id,business_name')
            ->when($propio, fn ($q) => $q->where('client_id', $propio))
            ->orderBy('name')
            ->get();

        // Clientes que todavía no tienen agente, para el desplegable de alta.
        $sinAgente = Client::query()
            ->when($propio, fn ($q) => $q->whereKey($propio))
            ->whereDoesntHave('aiAgent')
            ->orderBy('business_name')
            ->get(['id', 'business_name']);

        return Inertia::render('AI/Agents', [
            'agentes' => $agentes->map(fn (AiAgent $a) => $this->serializar($a)),

            'clientesSinAgente' => $sinAgente->map(fn (Client $c) => [
                'id'    => $c->id,
                'name'  => $c->business_name,
            ]),

            // Que LunAvalos pueda tener el suyo para su propio número, y que
            // no aparezca dos veces si ya existe.
            'puedeCrearPropio' => $propio === null
                && !AiAgent::whereNull('client_id')->exists(),

            // Sin credenciales el agente queda mudo y sin error visible: la
            // pantalla tiene que decirlo antes de que alguien lo encienda y se
            // pregunte por qué no contesta.
            'hayCredenciales' => filled(config('services.anthropic.api_key')),

            'modelos' => self::MODELOS,
        ]);
    }

    /**
     * Los modelos que ofrecemos, con lo único que de verdad decide entre ellos:
     * el costo. Los precios son por millón de tokens.
     *
     * No se lee de la API a propósito: es una lista corta que cambia pocas
     * veces al año, y una llamada de red para pintar un desplegable haría que
     * la pantalla dependiera de que Anthropic esté arriba.
     */
    private const MODELOS = [
        ['id' => 'claude-opus-5',   'nombre' => 'Opus 5',    'costo' => 'El más capaz — $5 / $25 por millón'],
        ['id' => 'claude-sonnet-5', 'nombre' => 'Sonnet 5',  'costo' => 'Equilibrado — $3 / $15 por millón'],
        ['id' => 'claude-haiku-4-5', 'nombre' => 'Haiku 4.5', 'costo' => 'El más barato y rápido — $1 / $5 por millón'],
    ];

    public function store(Request $request)
    {
        $datos = $request->validate([
            'client_id'           => 'nullable|integer|exists:clients,id',
            'name'                => 'required|string|max:255',
            'model'               => 'required|string|in:' . implode(',', array_column(self::MODELOS, 'id')),
            'system_prompt'       => 'nullable|string|max:20000',
            'disclosure'          => 'nullable|string|max:500',
            'monthly_token_limit' => 'nullable|integer|min:0',
            'enabled'             => 'boolean',
        ]);

        $this->autorizar($request, $datos['client_id'] ?? null);

        // Un agente por cliente: la columna es única, y sin esto el usuario
        // vería un error de base de datos en vez de una explicación.
        if (AiAgent::where('client_id', $datos['client_id'] ?? null)->exists()) {
            return back()->withErrors(['client_id' => 'Ese cliente ya tiene un agente.']);
        }

        AiAgent::create($this->normalizar($datos));

        return back()->with('success', 'Agente creado.');
    }

    public function update(Request $request, AiAgent $agent)
    {
        $this->autorizar($request, $agent->client_id);

        $datos = $request->validate([
            'name'                => 'required|string|max:255',
            'model'               => 'required|string|in:' . implode(',', array_column(self::MODELOS, 'id')),
            'system_prompt'       => 'nullable|string|max:20000',
            'disclosure'          => 'nullable|string|max:500',
            'monthly_token_limit' => 'nullable|integer|min:0',
            'enabled'             => 'boolean',
        ]);

        $agent->update($this->normalizar($datos));

        return back()->with('success', 'Agente actualizado.');
    }

    public function destroy(Request $request, AiAgent $agent)
    {
        $this->autorizar($request, $agent->client_id);

        $agent->delete();

        return back()->with('success', 'Agente eliminado.');
    }

    /**
     * Cómo quedaría el prompt con la ficha del cliente actual.
     *
     * Sin esto, dejar el prompt vacío es un acto de fe: no hay forma de saber
     * qué se le está diciendo al modelo hasta que un contacto real recibe una
     * respuesta rara.
     */
    public function preview(Request $request, AiAgent $agent, ConversationAgent $servicio)
    {
        $this->autorizar($request, $agent->client_id);

        return back()->with('preview', $servicio->promptDelSistema($agent));
    }

    private function normalizar(array $datos): array
    {
        // 0 en la UI significa "sin tope", que en la base es null.
        if (($datos['monthly_token_limit'] ?? null) === 0) {
            $datos['monthly_token_limit'] = null;
        }

        return $datos;
    }

    private function serializar(AiAgent $agente): array
    {
        $consumo = $agente->usage()->where('period', AiUsage::periodoActual())->first();
        $gastado = ($consumo?->input_tokens ?? 0) + ($consumo?->output_tokens ?? 0);

        return [
            'id'                  => $agente->id,
            'client_id'           => $agente->client_id,
            'cliente'             => $agente->client?->business_name ?? 'LunAvalos (número propio)',
            'name'                => $agente->name,
            'enabled'             => $agente->enabled,
            'model'               => $agente->model,
            'system_prompt'       => $agente->system_prompt,
            'disclosure'          => $agente->disclosure,
            'disclosure_efectivo' => $agente->avisoDeAutomatizacion(),
            'monthly_token_limit' => $agente->monthly_token_limit,

            'consumo' => [
                'periodo'    => AiUsage::periodoActual(),
                'gastado'    => $gastado,
                'cache'      => $consumo?->cache_read_tokens ?? 0,
                'mensajes'   => $consumo?->messages ?? 0,
                // El porcentaje se calcula aquí y no en el front para que la
                // regla de qué cuenta al tope viva en un solo sitio.
                'porcentaje' => $agente->monthly_token_limit
                    ? min(100, (int) round($gastado / $agente->monthly_token_limit * 100))
                    : null,
                'superado'   => $agente->superoElTope(),
            ],

            'puede_responder' => $agente->puedeResponder(),
        ];
    }
}
