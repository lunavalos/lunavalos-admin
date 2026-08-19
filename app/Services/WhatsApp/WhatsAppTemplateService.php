<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Plantillas de mensaje contra la Graph API.
 *
 * Van por `/{waba_id}/message_templates`, no por `/{phone_number_id}/messages`
 * como los envíos, y usan el permiso whatsapp_business_management en vez de
 * whatsapp_business_messaging. Por eso viven en su propio servicio y no dentro
 * de WhatsAppService.
 *
 * Aquí sí se lanzan excepciones: crear una plantilla es una acción explícita
 * del usuario y tiene que fallar a la vista. Es lo contrario del envío, donde
 * un fallo nunca debe tumbar la petición que lo originó.
 */
class WhatsAppTemplateService
{
    /**
     * Trae las plantillas que Meta tiene para esta WABA y las refleja en local.
     *
     * Es la fuente de verdad: una plantilla puede crearse, aprobarse o
     * deshabilitarse desde el Business Manager del cliente sin pasar por aquí.
     *
     * @return int cuántas se sincronizaron
     */
    public function sincronizar(WhatsAppAccount $cuenta): int
    {
        $limite = 200;

        $respuesta = $this->graph($cuenta)->get("/{$cuenta->waba_id}/message_templates", [
            'fields' => 'id,name,language,category,status,components,rejected_reason',
            'limit'  => $limite,
        ]);

        if (!$respuesta->successful()) {
            $this->reventar('no se pudieron leer las plantillas', $respuesta->json('error', []));
        }

        $remotas = $respuesta->json('data', []);
        $vistas  = 0;
        $vivas   = [];

        foreach ($remotas as $remota) {
            if (empty($remota['name']) || empty($remota['language'])) {
                continue;
            }

            $componentes = $remota['components'] ?? [];

            WhatsAppTemplate::updateOrCreate(
                [
                    'whatsapp_account_id' => $cuenta->id,
                    'name'                => $remota['name'],
                    'language'            => $remota['language'],
                ],
                [
                    'meta_id'         => $remota['id']              ?? null,
                    'category'        => $remota['category']        ?? 'UTILITY',
                    'status'          => $remota['status']          ?? WhatsAppTemplate::STATUS_PENDING,
                    'rejected_reason' => $remota['rejected_reason'] ?? null,
                    'components'      => $componentes,
                    'body_variables'  => WhatsAppTemplate::contarVariables(
                        $this->cuerpoDe($componentes)
                    ),
                ],
            );

            $vivas[] = $remota['name'] . '|' . $remota['language'];
            $vistas++;
        }

        // Meta es la fuente de verdad: una plantilla borrada allá tiene que
        // desaparecer de aquí, o el equipo la sigue viendo y la ofrece al enviar.
        //
        // Solo se poda si la respuesta cupo entera. Si vino llena puede haber
        // más páginas, y lo que falta no es "borrado en Meta" sino "no lo
        // pedimos" — podar ahí borraría plantillas válidas.
        if (count($remotas) < $limite) {
            $cuenta->templates()
                ->get(['id', 'name', 'language'])
                ->reject(fn ($p) => in_array($p->name . '|' . $p->language, $vivas, true))
                ->each(fn ($p) => $p->delete());
        }

        return $vistas;
    }

    /**
     * Crea la plantilla en Meta y la guarda como PENDING.
     *
     * Meta la revisa por su cuenta y puede tardar; el estado final llega por el
     * webhook message_template_status_update.
     *
     * @param array{name: string, language: string, category: string, header?: ?string, body: string, footer?: ?string, ejemplos?: list<string>} $datos
     */
    public function crear(WhatsAppAccount $cuenta, array $datos): WhatsAppTemplate
    {
        $componentes = $this->componentesDesde($datos);

        $respuesta = $this->graph($cuenta)->post("/{$cuenta->waba_id}/message_templates", [
            'name'       => $datos['name'],
            'language'   => $datos['language'],
            'category'   => $datos['category'],
            'components' => $componentes,
        ]);

        if (!$respuesta->successful()) {
            $this->reventar('Meta rechazó la plantilla', $respuesta->json('error', []));
        }

        return WhatsAppTemplate::updateOrCreate(
            [
                'whatsapp_account_id' => $cuenta->id,
                'name'                => $datos['name'],
                'language'            => $datos['language'],
            ],
            [
                'meta_id'         => $respuesta->json('id'),
                'category'        => $respuesta->json('category', $datos['category']),
                'status'          => $respuesta->json('status', WhatsAppTemplate::STATUS_PENDING),
                'rejected_reason' => null,
                'components'      => $componentes,
                'body_variables'  => WhatsAppTemplate::contarVariables($datos['body']),
            ],
        );
    }

    /**
     * Borra la plantilla en Meta y en local. Meta borra por nombre: se lleva
     * todos los idiomas de esa plantilla, así que aquí también.
     */
    public function eliminar(WhatsAppTemplate $plantilla): void
    {
        $cuenta = $plantilla->account;

        // El nombre va en la query, no en el cuerpo. Laravel manda los datos de
        // un DELETE como body, y Meta ahí no los lee: la petición salía sin
        // nombre y el borrado fallaba siempre.
        $query = http_build_query(['name' => $plantilla->name]);

        $respuesta = $this->graph($cuenta)->delete("/{$cuenta->waba_id}/message_templates?{$query}");

        if (!$respuesta->successful()) {
            $this->reventar('no se pudo borrar la plantilla', $respuesta->json('error', []));
        }

        WhatsAppTemplate::where('whatsapp_account_id', $cuenta->id)
            ->where('name', $plantilla->name)
            ->delete();
    }

    /**
     * De los campos del formulario al formato que espera Graph.
     *
     * Solo HEADER de texto, BODY y FOOTER. Los botones se dejan fuera a
     * propósito: multiplican los modos de fallo y no hacen falta para el caso
     * que resuelven las plantillas, que es reabrir la conversación.
     */
    private function componentesDesde(array $datos): array
    {
        $componentes = [];

        if (!empty($datos['header'])) {
            $componentes[] = [
                'type'   => 'HEADER',
                'format' => 'TEXT',
                'text'   => $datos['header'],
            ];
        }

        $cuerpo = ['type' => 'BODY', 'text' => $datos['body']];

        // Meta exige un valor de ejemplo por cada {{n}}: el revisor humano los
        // usa para entender qué va en cada hueco. Sin ellos la plantilla se
        // crea igual y el rechazo llega horas después, en revisión — el peor
        // modo de fallo posible, porque parece que funcionó.
        $ejemplos = array_values($datos['ejemplos'] ?? []);

        if ($ejemplos !== []) {
            $cuerpo['example'] = ['body_text' => [$ejemplos]];
        }

        $componentes[] = $cuerpo;

        if (!empty($datos['footer'])) {
            $componentes[] = ['type' => 'FOOTER', 'text' => $datos['footer']];
        }

        return $componentes;
    }

    private function cuerpoDe(array $componentes): string
    {
        foreach ($componentes as $componente) {
            if (($componente['type'] ?? '') === 'BODY') {
                return $componente['text'] ?? '';
            }
        }

        return '';
    }

    private function graph(WhatsAppAccount $cuenta)
    {
        $token = $cuenta->tokenParaEnviar();

        if (!$token) {
            throw new RuntimeException(
                'La cuenta de WhatsApp no tiene token. Vuelve a conectarla desde el cliente.'
            );
        }

        $version = config('services.whatsapp.graph_version', 'v26.0');

        return Http::baseUrl("https://graph.facebook.com/{$version}")
            ->withToken($token)
            ->timeout((int) config('services.whatsapp.timeout', 10))
            ->acceptJson();
    }

    private function reventar(string $que, array $error): never
    {
        Log::warning('whatsapp plantillas: ' . $que, [
            'codigo'  => $error['code']           ?? null,
            'detalle' => $error['error_user_msg'] ?? null,
            'mensaje' => $error['message']        ?? null,
        ]);

        // error_user_msg es el que explica el rechazo en términos de la
        // plantilla; message suele ser genérico.
        $detalle = $error['error_user_msg'] ?? $error['message'] ?? null;

        throw new RuntimeException($que . ($detalle ? '. Meta respondió: ' . $detalle : '.'));
    }
}
