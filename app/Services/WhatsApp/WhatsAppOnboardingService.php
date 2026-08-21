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
     * suscribe nuestra app al webhook y deja todo guardado.
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
        $token  = (string) config('services.whatsapp.token');

        if ($wabaId === '' || $token === '') {
            throw new RuntimeException(
                'Faltan WHATSAPP_BUSINESS_ACCOUNT_ID y/o WHATSAPP_TOKEN en la configuración.'
            );
        }

        $info = $this->infoDeWaba($wabaId, $token);

        $cuenta = WhatsAppAccount::updateOrCreate(
            ['waba_id' => $wabaId],
            [
                'name'          => $info['name'] ?? 'LunAvalos',
                'status'        => WhatsAppAccount::STATUS_ACTIVE,
                'last_error'    => null,
                'last_error_at' => null,
            ],
        );

        $this->sincronizarNumeros($cuenta, null, $token);
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
    private function sincronizarNumeros(WhatsAppAccount $cuenta, ?Client $client, string $token): void
    {
        $respuesta = $this->graph()->withToken($token)->get("/{$cuenta->waba_id}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating',
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
                'is_active'            => true,
            ];

            if ($client !== null) {
                // Embedded Signup: la WABA es del cliente, mandamos siempre.
                WhatsAppNumber::updateOrCreate(
                    ['phone_number_id' => $numero['id']],
                    $siempre + ['client_id' => $client->id],
                );

                continue;
            }

            // WABA propia: se respeta la asignación que ya tuviera el número.
            $fila = WhatsAppNumber::firstOrNew(['phone_number_id' => $numero['id']]);
            $fila->fill($siempre);

            if (!$fila->exists) {
                $fila->client_id = null;
            }

            $fila->save();
        }
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
