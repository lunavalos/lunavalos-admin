<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Plantillas de mensaje.
 *
 * Son el único camino que Meta entrega fuera de la ventana de 24 h, así que lo
 * que se cuida aquí es que no se pueda enviar algo que Meta va a rechazar
 * —una plantilla en revisión, o con los huecos mal rellenados— y que un
 * cliente no alcance las plantillas de otro.
 */
class WhatsAppTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(string $wabaId = '2436841820155807'): WhatsAppAccount
    {
        return WhatsAppAccount::create([
            'waba_id'      => $wabaId,
            'name'         => 'LunAvalos',
            'access_token' => 'token-de-prueba',
            'status'       => WhatsAppAccount::STATUS_ACTIVE,
        ]);
    }

    private function numero(WhatsAppAccount $cuenta, ?Client $client = null): WhatsAppNumber
    {
        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => $client?->id,
            'phone_number_id'      => (string) fake()->unique()->numerify('##############'),
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    private function plantilla(WhatsAppAccount $cuenta, array $extra = []): WhatsAppTemplate
    {
        return WhatsAppTemplate::create(array_merge([
            'whatsapp_account_id' => $cuenta->id,
            'meta_id'             => '111222333',
            'name'                => 'pedido_listo',
            'language'            => 'es_MX',
            'category'            => 'UTILITY',
            'status'              => WhatsAppTemplate::STATUS_APPROVED,
            'components'          => [['type' => 'BODY', 'text' => 'Hola {{1}}, tu pedido {{2}} está listo.']],
            'body_variables'      => 2,
        ], $extra));
    }

    private function staff(): User
    {
        return $this->conPermiso(User::factory()->create(['client_id' => null]));
    }

    /**
     * El módulo va cerrado por `Gestionar Plantillas WhatsApp`. Los usuarios de
     * las pruebas de scoping también lo llevan, para que el 403 que se
     * comprueba venga del cliente equivocado y no del permiso.
     *
     * Se suma `Responder Conversaciones` porque el bloque de envío de abajo
     * entra por `ConversationController::replyTemplate()`, que va cerrado por
     * ese otro permiso: mandar una plantilla es responderle al contacto.
     */
    private function conPermiso(User $usuario): User
    {
        $usuario->givePermissionTo(
            Permission::findOrCreate('Gestionar Plantillas WhatsApp', 'web'),
            Permission::findOrCreate('Responder Conversaciones', 'web'),
        );

        return $usuario;
    }

    // -----------------------------------------------------------------
    // Crear en Meta
    // -----------------------------------------------------------------

    public function test_crear_una_plantilla_la_manda_a_meta_y_la_guarda_como_pendiente(): void
    {
        $cuenta = $this->cuenta();

        Http::fake([
            '*/message_templates' => Http::response([
                'id'       => '987654321',
                'status'   => 'PENDING',
                'category' => 'UTILITY',
            ]),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.templates.store', $cuenta), [
                'name'     => 'aviso_cita',
                'language' => 'es_MX',
                'category' => 'UTILITY',
                'body'     => 'Hola {{1}}, tu cita es el {{2}}.',
                'footer'   => 'LunAvalos',
            ])
            ->assertRedirect();

        $plantilla = WhatsAppTemplate::where('name', 'aviso_cita')->first();

        $this->assertNotNull($plantilla);
        $this->assertSame(WhatsAppTemplate::STATUS_PENDING, $plantilla->status);
        $this->assertSame('987654321', $plantilla->meta_id);
        // Se derivan del cuerpo, no se piden al usuario.
        $this->assertSame(2, $plantilla->body_variables);

        Http::assertSent(function ($request) {
            $cuerpo = $request->data();

            return str_contains($request->url(), '/2436841820155807/message_templates')
                && $cuerpo['name'] === 'aviso_cita'
                // El pie va como componente FOOTER, no como campo suelto.
                && collect($cuerpo['components'])->contains(fn ($c) => $c['type'] === 'FOOTER');
        });
    }

    public function test_un_nombre_con_mayusculas_se_rechaza_sin_llamar_a_meta(): void
    {
        Http::fake();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.templates.store', $this->cuenta()), [
                'name'     => 'Aviso Cita',
                'language' => 'es_MX',
                'category' => 'UTILITY',
                'body'     => 'Hola.',
            ])
            ->assertSessionHasErrors('name');

        Http::assertNothingSent();
    }

    public function test_si_meta_rechaza_la_plantilla_no_se_guarda_nada(): void
    {
        $cuenta = $this->cuenta();

        Http::fake([
            '*/message_templates' => Http::response([
                'error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'Ese nombre ya existe.'],
            ], 400),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.templates.store', $cuenta), [
                'name'     => 'aviso_cita',
                'language' => 'es_MX',
                'category' => 'UTILITY',
                'body'     => 'Hola.',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, WhatsAppTemplate::count());
    }

    public function test_sincronizar_refleja_lo_que_tiene_meta(): void
    {
        $cuenta = $this->cuenta();

        Http::fake([
            '*/message_templates*' => Http::response(['data' => [[
                'id'         => '555',
                'name'       => 'bienvenida',
                'language'   => 'es_MX',
                'category'   => 'MARKETING',
                'status'     => 'APPROVED',
                'components' => [['type' => 'BODY', 'text' => 'Hola {{1}}.']],
            ]]]),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.templates.sync', $cuenta))
            ->assertRedirect();

        $plantilla = WhatsAppTemplate::where('name', 'bienvenida')->first();

        $this->assertSame(WhatsAppTemplate::STATUS_APPROVED, $plantilla->status);
        $this->assertSame(1, $plantilla->body_variables);
    }

    public function test_sin_el_permiso_no_se_entra_al_modulo(): void
    {
        $cuenta = $this->cuenta();

        Http::fake();

        // Usuario interno: client_id null, así que el scoping por cliente no lo
        // detiene. Lo único que lo para es el permiso.
        $sinPermiso = User::factory()->create(['client_id' => null]);

        $this->actingAs($sinPermiso)
            ->get(route('whatsapp.templates.index'))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->post(route('whatsapp.templates.store', $cuenta), [
                'name'     => 'aviso',
                'language' => 'es_MX',
                'category' => 'UTILITY',
                'body'     => 'Hola.',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_un_usuario_de_portal_no_puede_tocar_la_waba_de_otro_cliente(): void
    {
        $cuenta = $this->cuenta();
        $ajeno  = Client::create(['business_name' => 'Cliente B']);
        $this->numero($cuenta, $ajeno);

        $suyo = Client::create(['business_name' => 'Cliente A']);

        Http::fake();

        $this->actingAs($this->conPermiso(User::factory()->create(['client_id' => $suyo->id])))
            ->post(route('whatsapp.templates.store', $cuenta), [
                'name'     => 'aviso',
                'language' => 'es_MX',
                'category' => 'UTILITY',
                'body'     => 'Hola.',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Enviar con la ventana cerrada
    // -----------------------------------------------------------------

    private function conversacionConVentanaCerrada(WhatsAppNumber $numero): Conversation
    {
        return Conversation::create([
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5218443410326',
            'last_inbound_at'    => now()->subDays(3),
            'last_message_at'    => now()->subDays(3),
        ]);
    }

    public function test_enviar_una_plantilla_funciona_con_la_ventana_cerrada(): void
    {
        $cuenta       = $this->cuenta();
        $numero       = $this->numero($cuenta);
        $conversacion = $this->conversacionConVentanaCerrada($numero);
        $plantilla    = $this->plantilla($cuenta);

        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.PLANTILLA']]])]);

        $this->actingAs($this->staff())
            ->post(route('conversations.replyTemplate', $conversacion), [
                'template_id' => $plantilla->id,
                'parametros'  => ['Ana', 'A-42'],
            ])
            ->assertRedirect();

        $mensaje = $conversacion->messages()->first();

        $this->assertSame('wamid.PLANTILLA', $mensaje->wa_message_id);
        $this->assertSame(ConversationMessage::DELIVERY_SENT, $mensaje->delivery_status);
        // En el hilo tiene que leerse lo que recibió el contacto, no el nombre
        // de la plantilla.
        $this->assertSame('Hola Ana, tu pedido A-42 está listo.', $mensaje->body);

        Http::assertSent(function ($request) {
            $cuerpo = $request->data();

            return $cuerpo['type'] === 'template'
                && $cuerpo['template']['name'] === 'pedido_listo'
                && $cuerpo['template']['language']['code'] === 'es_MX'
                && $cuerpo['template']['components'][0]['parameters'][1]['text'] === 'A-42';
        });
    }

    public function test_una_plantilla_en_revision_no_se_puede_enviar(): void
    {
        $cuenta       = $this->cuenta();
        $conversacion = $this->conversacionConVentanaCerrada($this->numero($cuenta));
        $plantilla    = $this->plantilla($cuenta, ['status' => WhatsAppTemplate::STATUS_PENDING]);

        Http::fake();

        $this->actingAs($this->staff())
            ->post(route('conversations.replyTemplate', $conversacion), [
                'template_id' => $plantilla->id,
                'parametros'  => ['Ana', 'A-42'],
            ])
            ->assertSessionHasErrors('template_id');

        Http::assertNothingSent();
        $this->assertSame(0, $conversacion->messages()->count());
    }

    public function test_faltan_valores_para_los_huecos_y_no_se_manda(): void
    {
        $cuenta       = $this->cuenta();
        $conversacion = $this->conversacionConVentanaCerrada($this->numero($cuenta));
        $plantilla    = $this->plantilla($cuenta);

        Http::fake();

        $this->actingAs($this->staff())
            ->post(route('conversations.replyTemplate', $conversacion), [
                'template_id' => $plantilla->id,
                'parametros'  => ['Ana'],   // la plantilla pide dos
            ])
            ->assertSessionHasErrors('template_id');

        Http::assertNothingSent();
    }

    public function test_no_se_puede_enviar_una_plantilla_de_otra_waba(): void
    {
        $conversacion = $this->conversacionConVentanaCerrada($this->numero($this->cuenta()));
        $ajena        = $this->plantilla($this->cuenta('999888777'));

        Http::fake();

        $this->actingAs($this->staff())
            ->post(route('conversations.replyTemplate', $conversacion), [
                'template_id' => $ajena->id,
                'parametros'  => ['Ana', 'A-42'],
            ])
            ->assertSessionHasErrors('template_id');

        Http::assertNothingSent();
    }

    public function test_un_envio_rechazado_queda_marcado_como_fallido(): void
    {
        $cuenta       = $this->cuenta();
        $conversacion = $this->conversacionConVentanaCerrada($this->numero($cuenta));
        $plantilla    = $this->plantilla($cuenta);

        Http::fake(['*/messages' => Http::response(['error' => ['code' => 132000]], 400)]);

        $this->actingAs($this->staff())
            ->post(route('conversations.replyTemplate', $conversacion), [
                'template_id' => $plantilla->id,
                'parametros'  => ['Ana', 'A-42'],
            ])
            ->assertRedirect();

        $this->assertSame(
            ConversationMessage::DELIVERY_FAILED,
            $conversacion->messages()->first()->delivery_status,
        );
    }

    // -----------------------------------------------------------------
    // Webhook de aprobación
    // -----------------------------------------------------------------

    public function test_el_webhook_actualiza_el_estado_de_la_plantilla(): void
    {
        config(['services.whatsapp.app_secret' => 'secreto']);

        $cuenta    = $this->cuenta();
        $plantilla = $this->plantilla($cuenta, ['status' => WhatsAppTemplate::STATUS_PENDING]);

        $payload = [
            'entry' => [[
                'id'      => $cuenta->waba_id,
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => [
                        'event'               => 'REJECTED',
                        'message_template_id' => $plantilla->meta_id,
                        'reason'              => 'INVALID_FORMAT',
                    ],
                ]],
            ]],
        ];

        $this->postJson(route('whatsapp.webhook.receive'), $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secreto'),
        ])->assertOk();

        $plantilla->refresh();

        $this->assertSame(WhatsAppTemplate::STATUS_REJECTED, $plantilla->status);
        $this->assertSame('INVALID_FORMAT', $plantilla->rejected_reason);
    }

    public function test_el_webhook_limpia_el_motivo_cuando_meta_aprueba(): void
    {
        config(['services.whatsapp.app_secret' => 'secreto']);

        $cuenta    = $this->cuenta();
        $plantilla = $this->plantilla($cuenta, [
            'status'          => WhatsAppTemplate::STATUS_REJECTED,
            'rejected_reason' => 'INVALID_FORMAT',
        ]);

        $payload = [
            'entry' => [[
                'id'      => $cuenta->waba_id,
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => [
                        'event'               => 'APPROVED',
                        'message_template_id' => $plantilla->meta_id,
                        'reason'              => 'NONE',
                    ],
                ]],
            ]],
        ];

        $this->postJson(route('whatsapp.webhook.receive'), $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secreto'),
        ])->assertOk();

        $plantilla->refresh();

        $this->assertSame(WhatsAppTemplate::STATUS_APPROVED, $plantilla->status);
        $this->assertNull($plantilla->rejected_reason);
    }
}
