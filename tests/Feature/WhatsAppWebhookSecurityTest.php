<?php

namespace Tests\Feature;

use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El webhook es el único punto de entrada sin sesión del sistema: crea tickets
 * y mensajes con lo que reciba. Su autenticación es la firma de Meta.
 *
 * La salida sigue yendo por n8n (fase 4 del plan multi-WABA).
 */
class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET   = 'app-secret-de-prueba';
    private const VERIFY_TOKEN = 'verify-token-de-prueba';
    private const N8N_SECRET   = 'secreto-de-prueba';

    private function payload(string $waMessageId = 'wamid.TEST123'): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
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

    private function configurarSalida(): void
    {
        config([
            'services.n8n.shared_secret'        => self::N8N_SECRET,
            'services.n8n.whatsapp_webhook_url' => 'https://n8n.test/webhook/lunavalos',
        ]);

        Http::fake(['n8n.test/*' => Http::response(['wa_message_id' => 'wamid.ACK999'], 200)]);
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

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_rechaza_el_webhook_con_firma_invalida(): void
    {
        $this->configurarEntrada();

        $this->enviar($this->payload(), 'sha256=' . str_repeat('a', 64))
            ->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_rechaza_una_firma_valida_de_otro_cuerpo(): void
    {
        $this->configurarEntrada();

        // Firma correcta, pero calculada sobre un payload distinto: es el caso
        // que atrapa a quien firma el JSON reserializado en vez del cuerpo crudo.
        $otra = $this->firmar($this->payload('wamid.OTRO'))['firma'];

        $this->enviar($this->payload(), $otra)->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_rechaza_todo_si_no_hay_app_secret_configurado(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $this->enviar($this->payload(), 'sha256=loquesea')->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_acepta_el_webhook_con_firma_valida_y_crea_el_ticket(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();

        $this->enviar($this->payload())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('ticket_messages', [
            'wa_message_id' => 'wamid.TEST123',
            'direction'     => TicketMessage::DIRECTION_IN,
        ]);
    }

    public function test_no_duplica_mensajes_cuando_meta_reintenta_el_mismo_evento(): void
    {
        $this->configurarEntrada();
        $this->configurarSalida();

        foreach (range(1, 2) as $ignored) {
            $this->enviar($this->payload())->assertOk();
        }

        $this->assertSame(1, TicketMessage::where('wa_message_id', 'wamid.TEST123')->count());
    }

    // -----------------------------------------------------------------
    // Salida (todavía vía n8n)
    // -----------------------------------------------------------------

    public function test_el_saliente_va_a_n8n_con_el_secreto_y_sin_token_de_meta(): void
    {
        $this->configurarSalida();

        $waId = app(\App\Services\WhatsApp\WhatsAppService::class)
            ->sendText('5215512345678', 'Respuesta del equipo');

        $this->assertSame('wamid.ACK999', $waId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://n8n.test/webhook/lunavalos'
                && $request->hasHeader('X-N8n-Secret', self::N8N_SECRET)
                && $request['action'] === 'send_text'
                && $request['to'] === '5215512345678';
        });

        // Nada debe salir hacia Meta desde este sistema todavía.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
