<?php

namespace App\Http\Controllers;

use App\Models\ApiConsumer;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Las integraciones de la API de plataforma: klwebapp, las landings, n8n.
 *
 * El comando `api:consumidor` sigue existiendo —sirve para seeds y para
 * automatizar—, pero dejarlo como único camino tenía un problema que crece con
 * cada cliente: dar de alta exige SSH, y REVOCAR también. Si se filtra el token
 * de una landing, el tiempo de reacción no puede depender de que alguien pueda
 * entrar al servidor.
 *
 * Sobre el secreto en el navegador: el docblock de CreateApiConsumer advierte,
 * con razón, que una pantalla web deja el token en claro en el payload de
 * Inertia. Aquí se acota lo que se puede acotar —viaja UNA vez, por flash de
 * sesión, nunca como prop persistente, y no se puede volver a consultar— y se
 * asume el resto a cambio de poder apagar una integración en diez segundos.
 *
 * Interna a propósito: emitir un token es abrir una puerta al WhatsApp de un
 * cliente. Un usuario de portal no la abre ni para su propia empresa.
 */
class ApiConsumerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:Gestionar Integraciones')];
    }

    /**
     * El permiso ya cierra la puerta, pero un usuario de portal con el permiso
     * mal asignado no debe poder emitir tokens: `client_id` no null es la marca
     * de que la cuenta pertenece a un cliente y no al equipo.
     */
    private function soloInterno(Request $request): void
    {
        abort_if(
            $request->user()?->client_id !== null,
            403,
            'Las integraciones las administra el equipo de LunAvalos.'
        );
    }

    public function index(Request $request)
    {
        $this->soloInterno($request);

        return Inertia::render('Integraciones/Index', [
            'integraciones' => ApiConsumer::with(['client:id,business_name', 'createdBy:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (ApiConsumer $c) => $this->serializar($c)),

            'clientes' => Client::orderBy('business_name')->get(['id', 'business_name'])
                ->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->business_name]),

            'permisosDisponibles' => ApiConsumer::ABILITIES,

            // La base que hay que darle a quien integra, para no tener que
            // dictársela por chat.
            'baseUrl' => url('/api/v1'),
        ]);
    }

    public function store(Request $request)
    {
        $this->soloInterno($request);

        $datos = $this->validar($request);

        // El slug sale del nombre, así que la unicidad se comprueba sobre el
        // derivado y no sobre lo que escribió el usuario.
        if (ApiConsumer::where('slug', Str::slug($datos['name']))->exists()) {
            return back()->withErrors(['name' => 'Ya existe una integración con ese nombre.']);
        }

        $consumidor = ApiConsumer::create([
            'name'        => $datos['name'],
            'slug'        => Str::slug($datos['name']),
            'client_id'   => $datos['client_id'] ?? null,
            'webhook_url' => $datos['webhook_url'] ?? null,
            // Solo tiene sentido si hay a dónde entregar: es la llave con la
            // que el receptor verifica que la entrega salió de nosotros.
            'webhook_secret' => !empty($datos['webhook_url']) ? Str::random(64) : null,
            'status'      => ApiConsumer::STATUS_ACTIVE,
            'created_by'  => $request->user()->id,
        ]);

        $token = $consumidor->createToken(
            $consumidor->slug . '-' . now()->format('Ymd'),
            $datos['permisos'],
            !empty($datos['expira_dias']) ? now()->addDays((int) $datos['expira_dias']) : null,
        );

        return back()->with('credenciales', [
            'integracion' => $consumidor->name,
            'token'       => $token->plainTextToken,
            'secreto'     => $consumidor->webhook_secret,
        ]);
    }

    /**
     * Editar lo que no es secreto. El token no se toca aquí: cambiar permisos
     * exige reemitirlo, porque las habilidades viven en el token, no en la fila.
     */
    public function update(Request $request, ApiConsumer $integracion)
    {
        $this->soloInterno($request);

        $datos = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'client_id'   => ['nullable', 'integer', 'exists:clients,id'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        $slug = Str::slug($datos['name']);

        if (ApiConsumer::where('slug', $slug)->whereKeyNot($integracion->id)->exists()) {
            return back()->withErrors(['name' => 'Ya existe otra integración con ese nombre.']);
        }

        // Estrenar webhook donde no había exige secreto: sin él, el receptor no
        // puede distinguir nuestras entregas de las de cualquiera.
        $estrenaWebhook = !empty($datos['webhook_url']) && blank($integracion->webhook_url);

        $integracion->update([
            'name'        => $datos['name'],
            'slug'        => $slug,
            'client_id'   => $datos['client_id'] ?? null,
            'webhook_url' => $datos['webhook_url'] ?? null,
        ]);

        if ($estrenaWebhook) {
            $secreto = Str::random(64);
            $integracion->update(['webhook_secret' => $secreto]);

            return back()->with('credenciales', [
                'integracion' => $integracion->name,
                'token'       => null,
                'secreto'     => $secreto,
            ]);
        }

        return back();
    }

    /**
     * Reemitir el token. Es lo que se usa cuando se filtra, cuando caduca o
     * cuando hay que cambiarle los permisos.
     *
     * Los anteriores se borran: dejar vivo el viejo convierte la rotación en
     * un gesto sin efecto, que es peor que no rotar porque parece que sí.
     */
    public function rotateToken(Request $request, ApiConsumer $integracion)
    {
        $this->soloInterno($request);

        $datos = $request->validate([
            'permisos'    => ['required', 'array', 'min:1'],
            'permisos.*'  => ['required', Rule::in(ApiConsumer::ABILITIES)],
            'expira_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $integracion->tokens()->delete();

        $token = $integracion->createToken(
            $integracion->slug . '-' . now()->format('Ymd'),
            $datos['permisos'],
            !empty($datos['expira_dias']) ? now()->addDays((int) $datos['expira_dias']) : null,
        );

        return back()->with('credenciales', [
            'integracion' => $integracion->name,
            'token'       => $token->plainTextToken,
            'secreto'     => null,
        ]);
    }

    /** Rotar solo la llave de firma, sin tocar el token. */
    public function rotateSecret(Request $request, ApiConsumer $integracion)
    {
        $this->soloInterno($request);

        if (blank($integracion->webhook_url)) {
            return back()->withErrors([
                'webhook_url' => 'Esta integración no recibe webhooks: no hay secreto que rotar.',
            ]);
        }

        $secreto = Str::random(64);
        $integracion->update(['webhook_secret' => $secreto]);

        return back()->with('credenciales', [
            'integracion' => $integracion->name,
            'token'       => null,
            'secreto'     => $secreto,
        ]);
    }

    /**
     * Apagar y encender. Es el botón de emergencia, y por eso existe además de
     * borrar: apagar es instantáneo, reversible y conserva el rastro de que la
     * integración existió.
     */
    public function toggle(Request $request, ApiConsumer $integracion)
    {
        $this->soloInterno($request);

        $integracion->update([
            'status' => $integracion->estaActivo()
                ? ApiConsumer::STATUS_DISABLED
                : ApiConsumer::STATUS_ACTIVE,
        ]);

        return back();
    }

    public function destroy(Request $request, ApiConsumer $integracion)
    {
        $this->soloInterno($request);

        // Borrar la fila sin borrar los tokens dejaría credenciales huérfanas
        // que ya no se ven en ninguna pantalla.
        $integracion->tokens()->delete();
        $integracion->delete();

        return back();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'client_id'   => ['nullable', 'integer', 'exists:clients,id'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'permisos'    => ['required', 'array', 'min:1'],
            'permisos.*'  => ['required', Rule::in(ApiConsumer::ABILITIES)],
            'expira_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);
    }

    /**
     * Nunca el token ni el secreto: sus valores solo viajan por flash, en el
     * momento de emitirlos.
     */
    private function serializar(ApiConsumer $c): array
    {
        $token = $c->tokens()->latest('id')->first();

        return [
            'id'          => $c->id,
            'name'        => $c->name,
            'slug'        => $c->slug,
            'status'      => $c->status,
            'activo'      => $c->estaActivo(),
            'client_id'   => $c->client_id,
            'cliente'     => $c->client?->business_name,
            'atado'       => $c->estaAtado(),
            'webhook_url' => $c->webhook_url,
            'last_used_at' => $c->last_used_at?->toISOString(),
            'creada_por'  => $c->createdBy?->name,
            'token'       => $token ? [
                'nombre'       => $token->name,
                'permisos'     => $token->abilities ?? [],
                'expira_el'    => $token->expires_at?->toISOString(),
                'caducado'     => $token->expires_at?->isPast() ?? false,
                'last_used_at' => $token->last_used_at?->toISOString(),
            ] : null,
        ];
    }
}
