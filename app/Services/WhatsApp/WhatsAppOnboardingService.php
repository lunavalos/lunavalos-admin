<?php

namespace App\Services\WhatsApp;

use App\Models\Client;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Onboarding de la WABA de un cliente vía Embedded Signup.
 *
 * Meta no permite que el cliente nos pegue un token a mano: el acceso tiene que
 * concederse por este flujo. El SDK del navegador nos devuelve un `code` de
 * corta vida y el resto ocurre aquí, en el servidor, donde vive el App Secret.
 *
 * Todo el flujo es idempotente porque el cliente lo va a repetir: reconectar
 * después de revocar, o volver a entrar por error.
 *
 * ⚠️ Las firmas exactas de la Graph API cambian entre versiones. La forma del
 * flujo es estable; los campos concretos hay que verificarlos contra la doc
 * vigente de Embedded Signup al actualizar la versión.
 */
class WhatsAppOnboardingService
{
    /**
     * Cierra el flujo completo: canjea el code, lee la WABA y sus números,
     * los registra en Cloud API, suscribe nuestra app al webhook y deja todo
     * guardado.
     *
     * @throws RuntimeException si Meta rechaza cualquiera de los pasos.
     */
    public function conectar(Client $client, string $code, ?string $wabaIdSugerida = null): WhatsAppAccount
    {
        [$token, $expiraEn] = $this->canjearCode($code);
        $wabaId = $wabaIdSugerida ?: $this->resolverWabaId($token);

        $info = $this->infoDeWaba($wabaId, $token);

        $cuenta = WhatsAppAccount::updateOrCreate(
            ['waba_id' => $wabaId],
            [
                'name'             => $info['name'] ?? $client->business_name,
                'access_token'     => $token,
                'token_expires_at' => $expiraEn,
                'status'           => WhatsAppAccount::STATUS_ACTIVE,
                'last_error'       => null,
                'last_error_at'    => null,
                'connected_by'     => auth()->id(),
            ],
        );

        $this->sincronizarNumeros($cuenta, $client, $token);

        // Sin este paso no llega un solo mensaje: la WABA del cliente tiene que
        // quedar suscrita a NUESTRA app.
        $this->suscribirApp($wabaId, $token);

        return $cuenta;
    }

    /**
     * Registra la WABA propia de LunAvalos, la que ya vive en configuración.
     *
     * Embedded Signup no sirve para esto: ese flujo existe para que un negocio
     * ajeno nos comparta su cuenta, y cuando el portfolio dueño de la app es el
     * mismo, Meta no ofrece la WABA en la lista —no hay nada que conceder—.
     * Sin este camino, nuestro propio número no tiene dónde vivir y ni el
     * webhook puede enrutarlo ni la pantalla de plantillas tiene contra qué
     * trabajar.
     *
     * El token NO se guarda en la fila: `tokenParaEnviar()` cae al del system
     * user que está en configuración, que es exactamente lo que queremos aquí.
     * Guardarlo duplicaría el secreto sin ganar nada.
     *
     * `client_id` queda en null, que es lo que significa "número propio"
     * (ver §4 del plan): no se inventa un Client para representarnos.
     *
     * Idempotente: correrlo de nuevo refresca los números y vuelve a suscribir.
     *
     * @throws RuntimeException si falta configuración o Meta rechaza algún paso.
     */
    public function adoptarWabaPropia(): WhatsAppAccount
    {
        $wabaId = (string) config('services.whatsapp.business_account_id');

        if ($wabaId === '') {
            throw new RuntimeException(
                'Faltan WHATSAPP_BUSINESS_ACCOUNT_ID y/o WHATSAPP_TOKEN en la configuración.'
            );
        }

        return $this->adoptarWaba($wabaId);
    }

    /**
     * Adopta CUALQUIER WABA que viva en nuestro portfolio, y opcionalmente se
     * la asigna a un cliente.
     *
     * Es el tercer camino de alta, y el que el plan no contemplaba. Los otros
     * dos no cubren el caso:
     *
     *   - Embedded Signup exige un negocio ajeno que conceda acceso. Verificado
     *     contra el panel el 2026-09-18: el diálogo pinta nuestro propio
     *     portfolio en gris con el motivo «This Meta Business Account owns the
     *     app». No hay nada que conceder, así que no hay `code` que canjear.
     *   - `adoptarWabaPropia()` está clavada a la WABA de configuración, o sea
     *     una sola.
     *
     * Lo que queda en medio es lo que de verdad usamos: una WABA por cliente,
     * creada dentro de nuestro portfolio verificado. Hereda nuestra business
     * verification —el cliente no tramita nada—, y como el método de pago y las
     * plantillas son por WABA, cada cliente conserva su factura y no ve las
     * plantillas de los demás.
     *
     * El token no se guarda en la fila: `tokenParaEnviar()` cae al del system
     * user, que es quien tiene acceso a estas WABAs. Si Meta contesta que no
     * hay permiso sobre la WABA, casi siempre es que falta darle acceso a ese
     * system user en el panel.
     *
     * `$soloNumeros` existe porque Meta regala un número de prueba (+1 555…) a
     * cada WABA nueva y no siempre deja borrarlo. Sin filtro, ese número entra
     * como si fuera del cliente: aparece en su pantalla, `registrarNumero()`
     * intenta activarlo, y —lo peor— el cliente queda con dos números activos,
     * que es justo el caso en el que `ApiController::numeroDeEnvio()` falla en
     * vez de adivinar. Ya nos pasó una vez con el +1 555 628-6220 (§10).
     *
     * Idempotente: correrlo de nuevo refresca los números y vuelve a suscribir.
     *
     * @param  string[]  $soloNumeros  phone_number_id a asignar. Vacío = todos.
     *
     * @throws RuntimeException si falta configuración o Meta rechaza algún paso.
     */
    public function adoptarWaba(string $wabaId, ?Client $client = null, array $soloNumeros = []): WhatsAppAccount
    {
        $token = (string) config('services.whatsapp.token');

        if ($token === '') {
            throw new RuntimeException(
                'Faltan WHATSAPP_BUSINESS_ACCOUNT_ID y/o WHATSAPP_TOKEN en la configuración.'
            );
        }

        $info = $this->infoDeWaba($wabaId, $token);

        $cuenta = WhatsAppAccount::updateOrCreate(
            ['waba_id' => $wabaId],
            [
                'name'          => $info['name'] ?? $client?->business_name ?? 'LunAvalos',
                'status'        => WhatsAppAccount::STATUS_ACTIVE,
                'last_error'    => null,
                'last_error_at' => null,
            ],
        );

        $this->sincronizarNumeros($cuenta, $client, $token, $soloNumeros);
        $this->suscribirApp($wabaId, $token);

        return $cuenta;
    }

    /**
     * Canjea el code de corta vida por el token del negocio.
     *
     * La configuración de Embedded Signup que usamos emite tokens con 60 días
     * de vida, así que Meta devuelve `expires_in`. Guardarlo es lo único que
     * permite avisar antes de que el acceso muera en silencio; si algún día la
     * configuración pasa a emitir tokens sin caducidad, `expires_in` no viene y
     * la expiración queda en null, que es justamente lo que significa.
     *
     * @return array{0: string, 1: ?\Illuminate\Support\Carbon}
     */
    private function canjearCode(string $code): array
    {
        $respuesta = $this->graph()->get('/oauth/access_token', [
            'client_id'     => config('services.whatsapp.app_id'),
            'client_secret' => config('services.whatsapp.app_secret'),
            'code'          => $code,
        ]);

        $token = $respuesta->json('access_token');

        if (!$respuesta->successful() || !$token) {
            $this->reventar('no se pudo canjear el code', $respuesta->json('error', []));
        }

        $expiraEn = (int) $respuesta->json('expires_in', 0);

        return [$token, $expiraEn > 0 ? now()->addSeconds($expiraEn) : null];
    }

    /**
     * Cuando el SDK no nos dice qué WABA se concedió, se lee del propio token.
     */
    private function resolverWabaId(string $token): string
    {
        $respuesta = $this->graph()->withToken($token)->get('/debug_token', [
            'input_token' => $token,
        ]);

        $wabaId = collect($respuesta->json('data.granular_scopes', []))
            ->firstWhere('scope', 'whatsapp_business_management')['target_ids'][0] ?? null;

        if (!$wabaId) {
            $this->reventar('el token no trae una WABA concedida', $respuesta->json('error', []));
        }

        return $wabaId;
    }

    private function infoDeWaba(string $wabaId, string $token): array
    {
        $respuesta = $this->graph()->withToken($token)->get("/{$wabaId}", [
            'fields' => 'id,name,currency,timezone_id',
        ]);

        if (!$respuesta->successful()) {
            $this->reventar('no se pudo leer la WABA', $respuesta->json('error', []));
        }

        return $respuesta->json();
    }

    /**
     * Los números que Meta reporta en una WABA, sin escribir nada.
     *
     * Existe para poder enseñarlos ANTES de adoptar. Con `adoptarWaba()` hay
     * que decidir cuáles son del cliente, y esa decisión no se puede tomar a
     * ciegas: toda WABA nueva trae el número de prueba que Meta regala, y
     * distinguirlo del bueno exige verlos.
     *
     * @return array<int, array{id: string, display_phone_number: string, verified_name: ?string, status: ?string, quality_rating: ?string}>
     *
     * @throws RuntimeException si Meta rechaza la lectura.
     */
    public function numerosDe(string $wabaId): array
    {
        $token = (string) config('services.whatsapp.token');

        $respuesta = $this->graph()->withToken($token)->get("/{$wabaId}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,status',
        ]);

        if (!$respuesta->successful()) {
            $this->reventar('no se pudieron leer los números', $respuesta->json('error', []));
        }

        return array_values(array_filter(
            $respuesta->json('data', []),
            fn ($n) => !empty($n['id']),
        ));
    }

    /**
     * Los números que trae la WABA. Se hace upsert por phone_number_id para que
     * reconectar no duplique ni pierda las conversaciones ya asociadas.
     */
    /**
     * Trae los números de la WABA y los deja sincronizados.
     *
     * **El dueño del número solo se fija cuando lo sabemos.** Con `$client`
     * llegamos desde Embedded Signup: la WABA es de ese cliente y todos sus
     * números son suyos, así que mandamos.
     *
     * Con `$client` null llegamos desde `adoptarWabaPropia()`, y ahí null no
     * significa "de nadie" sino "no lo decide este proceso": nuestra WABA puede
     * alojar números de varios clientes (§4), asignados a mano. Escribir null
     * encima desasignaría el número de Macadam cada vez que alguien vuelve a
     * correr el comando —en silencio, y el síntoma aparecería después como
     * conversaciones que el cliente dejó de ver y un agente de IA que responde
     * con el prompt equivocado—.
     *
     * Por eso `client_id` va en los valores de CREACIÓN, no en los de
     * actualización, cuando no hay cliente que imponer.
     */
    private function sincronizarNumeros(
        WhatsAppAccount $cuenta,
        ?Client $client,
        string $token,
        array $soloNumeros = [],
    ): void {
        $respuesta = $this->graph()->withToken($token)->get("/{$cuenta->waba_id}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,status',
        ]);

        if (!$respuesta->successful()) {
            $this->reventar('no se pudieron leer los números', $respuesta->json('error', []));
        }

        foreach ($respuesta->json('data', []) as $numero) {
            if (empty($numero['id'])) {
                continue;
            }

            $siempre = [
                'whatsapp_account_id'  => $cuenta->id,
                'display_phone_number' => $numero['display_phone_number'] ?? $numero['id'],
                'verified_name'        => $numero['verified_name']  ?? null,
                'quality_rating'       => $numero['quality_rating'] ?? null,
            ];

            // Solo cuando Meta lo manda: escribir null encima borraría el
            // estado que ya conocíamos y volvería a disparar el registro.
            if (array_key_exists('status', $numero)) {
                $siempre['status'] = $numero['status'];
            }

            // Sin filtro manda el cliente sobre toda la WABA, como siempre.
            // Con filtro solo son suyos los números que se nombraron: el resto
            // —el de prueba que Meta regala— se guarda sin dueño.
            $asignable = $client !== null
                && ($soloNumeros === [] || in_array($numero['id'], $soloNumeros, true));

            if ($asignable) {
                $fila = WhatsAppNumber::updateOrCreate(
                    ['phone_number_id' => $numero['id']],
                    $siempre + ['client_id' => $client->id, 'is_active' => true],
                );
            } else {
                // WABA propia: se respeta la asignación que ya tuviera el número.
                $fila = WhatsAppNumber::firstOrNew(['phone_number_id' => $numero['id']]);
                $fila->fill($siempre);

                if (!$fila->exists) {
                    $fila->client_id = null;
                }

                if ($soloNumeros === []) {
                    // Sin filtro no hay número ajeno que apagar: todos son
                    // nuestros. Reactivar aquí es lo que permite volver de un
                    // `desconectar()`.
                    $fila->is_active = true;
                } elseif (!$fila->exists) {
                    // Con filtro, lo que nadie pidió adoptar nace apagado: la
                    // fila existe para que el webhook sepa enrutarlo, pero no
                    // se ofrece para enviar.
                    //
                    // Solo al crearlo. Apagar uno que YA estaba asignado le
                    // tumbaría el canal a su cliente en silencio, que es el
                    // mismo error que este método ya evita con `client_id`.
                    $fila->is_active = false;
                }

                $fila->save();
            }

            $this->registrarNumero($fila, $token, $asignable);
        }
    }

    /**
     * Activa el número en Cloud API.
     *
     * Es el paso que faltaba y sin el cual el resto del onboarding no sirve de
     * nada: la WABA queda concedida, el webhook suscrito y el número guardado,
     * pero `POST /{phone_number_id}/messages` falla porque para Meta ese
     * número todavía no vive en Cloud API.
     *
     * Registrar exige fijar el PIN de verificación en dos pasos. Lo generamos
     * nosotros y lo guardamos cifrado, porque es lo único que permite volver a
     * registrar el número después —o entregárselo al cliente si algún día se
     * lleva su número a otro proveedor—.
     *
     * **No revienta el flujo.** Para cuando esto corre, la cuenta y el webhook
     * ya están guardados, y tirar la petición dejaría al cliente sin entrada
     * de mensajes por un fallo que solo afecta a la salida. Pero tampoco se
     * traga: el motivo queda en `registration_error` y la pantalla lo muestra.
     */
    private function registrarNumero(WhatsAppNumber $numero, string $token, bool $esDeCliente): void
    {
        if (!$numero->necesitaRegistro()) {
            // Ya activo. Volver a registrarlo no aporta nada y, si el cliente
            // fijó su propio PIN, solo produce un 133005 en cada sincronización.
            if ($numero->registered_at === null || $numero->registration_error !== null) {
                $numero->forceFill([
                    'registered_at'      => $numero->registered_at ?? now(),
                    'registration_error' => null,
                ])->save();
            }

            return;
        }

        // Meta no nos dijo el estado. En la WABA de un cliente recién
        // concedida, no registrar es garantizar que no pueda enviar; en la
        // propia, el número ya lleva meses vivo y registrarlo a ciegas sería
        // tocar producción sin motivo.
        if ($numero->status === null && !$esDeCliente) {
            return;
        }

        $pin = $numero->registration_pin ?: $this->pinNuevo();

        $respuesta = $this->graph()->withToken($token)->post("/{$numero->phone_number_id}/register", [
            'messaging_product' => 'whatsapp',
            'pin'               => $pin,
        ]);

        if ($respuesta->successful()) {
            $numero->forceFill([
                'status'             => WhatsAppNumber::ESTADO_CONECTADO,
                'registration_pin'   => $pin,
                'registered_at'      => now(),
                'registration_error' => null,
            ])->save();

            return;
        }

        $error = $respuesta->json('error', []);

        Log::warning('whatsapp onboarding: no se pudo registrar el número', [
            'phone_number_id' => $numero->phone_number_id,
            'codigo'          => $error['code']    ?? null,
            'mensaje'         => $error['message'] ?? null,
        ]);

        $numero->forceFill([
            'registration_error' => $this->explicarFalloDeRegistro($error),
        ])->save();
    }

    /**
     * Los dos códigos que de verdad aparecen piden una acción del cliente, no
     * nuestra. Decirlo aquí evita que alguien se quede mirando un mensaje de
     * Meta que no explica qué hacer.
     */
    private function explicarFalloDeRegistro(array $error): string
    {
        $mensaje = $error['message'] ?? 'Meta rechazó el registro.';

        return match ((int) ($error['code'] ?? 0)) {
            133005 => 'El número ya tiene verificación en dos pasos con otro PIN. '
                . 'El cliente debe desactivarla desde WhatsApp Manager para que podamos registrarlo.',
            133006 => 'El número aún no está verificado por Meta. '
                . 'El cliente tiene que completar la verificación del número antes de poder enviar.',
            default => $mensaje,
        };
    }

    private function pinNuevo(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function suscribirApp(string $wabaId, string $token): void
    {
        $respuesta = $this->graph()->withToken($token)->post("/{$wabaId}/subscribed_apps");

        if (!$respuesta->successful()) {
            $this->reventar('no se pudo suscribir la app al webhook', $respuesta->json('error', []));
        }
    }

    /**
     * Desconectar por nuestro lado. El cliente también puede revocarnos desde
     * su Business Manager sin avisarnos, y para eso está el estado `revoked`.
     */
    public function desconectar(WhatsAppAccount $cuenta): void
    {
        try {
            $this->graph()
                ->withToken($cuenta->tokenParaEnviar())
                ->delete("/{$cuenta->waba_id}/subscribed_apps");
        } catch (\Throwable $e) {
            // Que Meta falle no debe impedir que dejemos de usar el token.
            Log::warning('whatsapp: fallo al desuscribir la app', [
                'waba_id' => $cuenta->waba_id,
                'error'   => $e->getMessage(),
            ]);
        }

        $cuenta->update([
            'status'       => WhatsAppAccount::STATUS_REVOKED,
            'access_token' => null,
        ]);

        $cuenta->numbers()->update(['is_active' => false]);
    }

    private function graph()
    {
        $version = config('services.whatsapp.graph_version', 'v26.0');

        return Http::baseUrl("https://graph.facebook.com/{$version}")
            ->timeout((int) config('services.whatsapp.timeout', 10))
            ->acceptJson();
    }

    private function reventar(string $que, array $error): never
    {
        Log::warning('whatsapp onboarding: ' . $que, [
            'codigo'  => $error['code']    ?? null,
            'mensaje' => $error['message'] ?? null,
        ]);

        throw new RuntimeException(
            'No se pudo conectar WhatsApp: ' . $que
            . ($error['message'] ?? '' ? '. Meta respondió: ' . $error['message'] : '.')
        );
    }
}
