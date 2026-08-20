<?php

namespace Tests\Feature;

use App\Jobs\MarkWhatsAppMessageRead;
use App\Events\ConversationMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * El webhook es el único punto de entrada sin sesión del sistema: crea tickets
 * y mensajes con lo que reciba. Su autenticación es la firma de Meta.
 *
 * La salida va directo a graph.facebook.com: n8n ya no participa en ninguna
 * de las dos direcciones.
 */
class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET      = 'app-secret-de-prueba';
    private const VERIFY_TOKEN    = 'verify-token-de-prueba';
    private const TOKEN           = 'token-de-system-user';
    private const PHONE_NUMBER_ID = '1230737580126123';
    private const WABA_ID         = '2436841820155807';

    /**
     * El webhook enruta por WABA y por número: sin estos registros el evento
     * se descarta, que es justo lo que debe pasar con una WABA ajena.
     */
    private function registrarNumero(): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::create([
            'name'    => 'LunAvalos',
            'waba_id' => self::WABA_ID,
        ]);

        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => null,   // número propio, sin cliente externo
            'phone_number_id'      => self::PHONE_NUMBER_ID,
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    private function payload(string $waMessageId = 'wamid.TEST123', ?string $wabaId = null): array
    {
        return [
            'entry' => [[
                'id'      => $wabaId ?? self::WABA_ID,
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => self::PHONE_NUMBER_ID],
                        'contacts' => [['wa_id' => '5215512345678', 'profile' => ['name' => 'Cliente']]],
                        'messages' => [[
                            'id'   => $waMessageId,
                            'from' => '5215512345678',
                            'type' => 'text',
                            'text' => ['body' => 'Hola, necesito soporte'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function payloadDeEstado(string $waMessageId, string $estado): array
    {
        return [
            'entry' => [[
                'id'      => self::WABA_ID,
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => self::PHONE_NUMBER_ID],
                        'statuses' => [[
                            'id'     => $waMessageId,
                            'status' => $estado,
                            'errors' => [['title' => 'Message failed to send']],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * Meta firma el cuerpo crudo. Reproducimos exactamente el JSON que enviará
     * el cliente HTTP de pruebas para que la firma corresponda.
     */
    private function firmar(array $payload): array
    {
        $cuerpo = json_encode($payload);

        return [
            'cuerpo' => $cuerpo,
            'firma'  => 'sha256=' . hash_hmac('sha256', $cuerpo, self::APP_SECRET),
        ];
    }

    private function enviar(array $payload, ?string $firma = null)
    {
        ['cuerpo' => $cuerpo, 'firma' => $firmaValida] = $this->firmar($payload);

        return $this->call(
            'POST',
            '/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'          => 'application/json',
                'HTTP_ACCEPT'           => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $firma ?? $firmaValida,
            ],
            $cuerpo
        );
    }

    private function configurarEntrada(): void
    {
        config([
            'services.whatsapp.app_secret'   => self::APP_SECRET,
            'services.whatsapp.verify_token' => self::VERIFY_TOKEN,
        ]);
    }

    /**
     * Solo las credenciales. Separado del fake porque Http::fake() fusiona los
     * stubs en vez de reemplazarlos: un segundo fake sobre el mismo patrón no
     * sustituye al primero, así que los tests que necesitan una respuesta de
     * error deben registrar el suyo antes de que se registre el de éxito.
     */
    private function configurarCredenciales(): void
    {
        config([
            'services.whatsapp.token'           => self::TOKEN,
            'services.whatsapp.phone_number_id' => self::PHONE_NUMBER_ID,
            'services.whatsapp.graph_version'   => 'v26.0',
        ]);
    }

    private function configurarSalida(): void
    {
        $this->configurarCredenciales();

        Http::fake([
            'graph.facebook.com/*' => Http::response(
                ['messages' => [['id' => 'wamid.ACK999']]],
                200
            ),
        ]);
    }

    // -----------------------------------------------------------------
    // Handshake (GET)
    // -----------------------------------------------------------------

    public function test_el_handshake_devuelve_el_challenge_como_texto_plano(): void
    {
        $this->configurarEntrada();

        $respuesta = $this->get('/whatsapp/webhook?hub.mode=subscribe'
            . '&hub.verify_token=' . self::VERIFY_TOKEN
            . '&hub.challenge=1234567890');

        $respuesta->assertOk();
        // Tal cual, sin comillas ni envoltura JSON: si no, Meta rechaza la suscripción.
        $this->assertSame('1234567890', $respuesta->getContent());
        $this->assertStringStartsWith('text/plain', $respuesta->headers->get('Content-Type'));
    }

    public function test_el_handshake_rechaza_un_verify_token_incorrecto(): void
    {
        $this->configurarEntrada();

        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=equivocado&hub.challenge=123')
            ->assertStatus(403);
    }

    public function test_el_handshake_rechaza_todo_si_no_hay_verify_token_configurado(): void
    {
        config(['services.whatsapp.verify_token' => null]);

        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=123')
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Firma (POST)
    // -----------------------------------------------------------------

    public function test_rechaza_el_webhook_sin_firma(): void
    {
        $this->configurarEntrada();

        $this->postJson('/whatsapp/webhook', $this->payload())
            ->assertStatus(401);

        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_rechaza_el_webhook_con_firma_invalida(): void
    {
        $this->configurarEntrada();

        $this->enviar($this->payload(), 'sha256=' . str_repeat('a', 64))
            ->assertStatus(401);

        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_rechaza_una_firma_valida_de_otro_cuerpo(): void
    {
        $this->configurarEntrada();

        // Firma correcta, pero calculada sobre un payload distinto: es el caso
        // que atrapa a quien firma el JSON reserializado en vez del cuerpo crudo.
        $otra = $this->firmar($this->payload('wamid.OTRO'))['firma'];

        $this->enviar($this->payload(), $otra)->assertStatus(401);

        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_rechaza_todo_si_no_hay_app_secret_configurado(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $this->enviar($this->payload(), 'sha256=loquesea')->assertStatus(401);

        $this->assertDatabaseCount('conversation_messages', 0);
    }

    // -----------------------------------------------------------------
    // Enrutado y conversaciones
    // -----------------------------------------------------------------

    public function test_acepta_el_webhook_con_firma_valida_y_abre_la_conversacion(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();
        $numero = $this->registrarNumero();

        $this->enviar($this->payload())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $conversacion = Conversation::firstWhere('contact_wa_id', '5215512345678');

        $this->assertNotNull($conversacion);
        $this->assertSame($numero->id, $conversacion->whatsapp_number_id);
        $this->assertSame('Cliente', $conversacion->contact_name);
        $this->assertNotNull($conversacion->last_inbound_at);
        $this->assertSame(1, $conversacion->unread_count);

        $this->assertDatabaseHas('conversation_messages', [
            'wa_message_id' => 'wamid.TEST123',
            'direction'     => ConversationMessage::DIRECTION_IN,
            'author_type'   => ConversationMessage::AUTHOR_CONTACT,
            'body'          => 'Hola, necesito soporte',
        ]);
    }

    public function test_el_mismo_contacto_reutiliza_su_conversacion(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();
        $this->registrarNumero();

        $this->enviar($this->payload('wamid.UNO'))->assertOk();
        $this->enviar($this->payload('wamid.DOS'))->assertOk();

        // La razón de ser del módulo: un contacto tiene un solo hilo, no un
        // ticket nuevo por cada mensaje.
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, ConversationMessage::count());
    }

    public function test_ignora_eventos_de_una_waba_que_no_administramos(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();
        $this->registrarNumero();

        $this->enviar($this->payload('wamid.AJENO', '999999999999999'))->assertOk();

        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_no_duplica_mensajes_cuando_meta_reintenta_el_mismo_evento(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();
        $this->registrarNumero();

        foreach (range(1, 2) as $ignored) {
            $this->enviar($this->payload())->assertOk();
        }

        $this->assertSame(1, ConversationMessage::where('wa_message_id', 'wamid.TEST123')->count());
    }

    public function test_un_entrante_se_emite_para_que_la_bandeja_se_actualice_sola(): void
    {
        Event::fake([ConversationMessageSent::class]);
        $this->configurarEntrada();
        $this->configurarSalida();
        $this->registrarNumero();

        $this->enviar($this->payload())->assertOk();

        // El tiempo real solo cubría lo que sale de la app: al llegar una
        // respuesta del contacto había que recargar la pantalla, que es lo
        // contrario de para lo que existe una bandeja.
        Event::assertDispatched(
            ConversationMessageSent::class,
            fn ($e) => $e->message->direction === ConversationMessage::DIRECTION_IN,
        );
    }

    public function test_marcar_como_leido_se_despacha_a_la_cola(): void
    {
        Bus::fake();
        $this->configurarEntrada();
        $this->configurarSalida();
        $this->registrarNumero();

        $this->enviar($this->payload())->assertOk();

        // Meta exige un 200 rápido: ninguna llamada a Graph puede vivir dentro
        // del request o el webhook se degrada a fuerza de reintentos.
        Bus::assertDispatched(MarkWhatsAppMessageRead::class);
    }

    public function test_los_statuses_actualizan_el_estado_de_entrega(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();
        $numero = $this->registrarNumero();

        $conversacion = Conversation::create([
            'client_id'          => null,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5215512345678',
        ]);
        $conversacion->messages()->create([
            'author_type'   => ConversationMessage::AUTHOR_STAFF,
            'direction'     => ConversationMessage::DIRECTION_OUT,
            'wa_message_id' => 'wamid.SALIENTE1',
            'body'          => 'Respuesta del equipo',
        ]);

        $this->enviar($this->payloadDeEstado('wamid.SALIENTE1', 'failed'))->assertOk();

        $this->assertDatabaseHas('conversation_messages', [
            'wa_message_id'   => 'wamid.SALIENTE1',
            'delivery_status' => ConversationMessage::DELIVERY_FAILED,
            'delivery_error'  => 'Message failed to send',
        ]);
    }

    // -----------------------------------------------------------------
    // Salida (Graph directo)
    // -----------------------------------------------------------------

    private function servicio(): \App\Services\WhatsApp\WhatsAppService
    {
        return app(\App\Services\WhatsApp\WhatsAppService::class);
    }

    public function test_el_saliente_va_a_graph_con_el_token_y_devuelve_el_wamid(): void
    {
        $this->configurarSalida();

        $waId = $this->servicio()->sendText('5215512345678', 'Respuesta del equipo');

        $this->assertSame('wamid.ACK999', $waId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v26.0/'
                    . self::PHONE_NUMBER_ID . '/messages'
                && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN)
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '5215512345678'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Respuesta del equipo';
        });
    }

    public function test_marcar_como_leido_manda_el_status_read(): void
    {
        $this->configurarSalida();

        $this->servicio()->markAsRead('wamid.ENTRANTE1');

        Http::assertSent(function ($request) {
            return $request['status'] === 'read'
                && $request['message_id'] === 'wamid.ENTRANTE1';
        });
    }

    public function test_permite_enviar_desde_otro_numero_y_con_otro_token(): void
    {
        $this->configurarSalida();

        // Es la puerta para multi-WABA: el llamador podrá pasar el número y el
        // token del cliente sin que cambie la firma para el resto del sistema.
        $this->servicio()->sendText('5215512345678', 'Hola', '999888777', 'token-del-cliente');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/999888777/messages')
                && $request->hasHeader('Authorization', 'Bearer token-del-cliente');
        });
    }

    public function test_devuelve_null_sin_lanzar_cuando_meta_rechaza_el_envio(): void
    {
        $this->configurarCredenciales();

        // 131047: fuera de la ventana de 24 h. Es el fallo más común en
        // producción y no debe tumbar la petición que lo originó.
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Re-engagement message', 'code' => 131047],
        ], 400)]);

        $this->assertNull($this->servicio()->sendText('5215512345678', 'Respuesta tardía'));
    }

    public function test_no_llama_a_meta_si_falta_configuracion(): void
    {
        config([
            'services.whatsapp.token'           => null,
            'services.whatsapp.phone_number_id' => null,
        ]);
        Http::fake();

        $this->assertNull($this->servicio()->sendText('5215512345678', 'Hola'));

        Http::assertNothingSent();
    }
}
