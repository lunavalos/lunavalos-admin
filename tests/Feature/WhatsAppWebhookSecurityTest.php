<?php

namespace Tests\Feature;

use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secreto-de-prueba';

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

    public function test_rechaza_el_webhook_sin_secreto(): void
    {
        config(['services.n8n.shared_secret' => self::SECRET]);

        $this->postJson('/whatsapp/webhook', $this->payload())
            ->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_rechaza_el_webhook_con_secreto_incorrecto(): void
    {
        config(['services.n8n.shared_secret' => self::SECRET]);

        $this->withHeader('X-N8n-Secret', 'secreto-equivocado')
            ->postJson('/whatsapp/webhook', $this->payload())
            ->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_rechaza_todo_si_no_hay_secreto_configurado(): void
    {
        config(['services.n8n.shared_secret' => null]);

        $this->postJson('/whatsapp/webhook', $this->payload())
            ->assertStatus(401);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_acepta_el_webhook_con_el_secreto_correcto_y_crea_el_ticket(): void
    {
        config([
            'services.n8n.shared_secret'        => self::SECRET,
            'services.n8n.whatsapp_webhook_url' => 'https://n8n.test/webhook/lunavalos',
        ]);

        Http::fake([
            'n8n.test/*' => Http::response(['wa_message_id' => 'wamid.ACK999'], 200),
        ]);

        $this->withHeader('X-N8n-Secret', self::SECRET)
            ->postJson('/whatsapp/webhook', $this->payload())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('ticket_messages', [
            'wa_message_id' => 'wamid.TEST123',
            'direction'     => TicketMessage::DIRECTION_IN,
        ]);
    }

    public function test_no_duplica_mensajes_cuando_n8n_reintenta_el_mismo_evento(): void
    {
        config([
            'services.n8n.shared_secret'        => self::SECRET,
            'services.n8n.whatsapp_webhook_url' => 'https://n8n.test/webhook/lunavalos',
        ]);

        Http::fake(['n8n.test/*' => Http::response(['wa_message_id' => 'wamid.ACK999'], 200)]);

        foreach (range(1, 2) as $ignored) {
            $this->withHeader('X-N8n-Secret', self::SECRET)
                ->postJson('/whatsapp/webhook', $this->payload())
                ->assertOk();
        }

        $this->assertSame(1, TicketMessage::where('wa_message_id', 'wamid.TEST123')->count());
    }

    public function test_el_saliente_va_a_n8n_con_el_secreto_y_sin_token_de_meta(): void
    {
        config([
            'services.n8n.shared_secret'        => self::SECRET,
            'services.n8n.whatsapp_webhook_url' => 'https://n8n.test/webhook/lunavalos',
        ]);

        Http::fake(['n8n.test/*' => Http::response(['wa_message_id' => 'wamid.OUT1'], 200)]);

        $waId = app(\App\Services\WhatsApp\WhatsAppService::class)
            ->sendText('5215512345678', 'Respuesta del equipo');

        $this->assertSame('wamid.OUT1', $waId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://n8n.test/webhook/lunavalos'
                && $request->hasHeader('X-N8n-Secret', self::SECRET)
                && $request['action'] === 'send_text'
                && $request['to'] === '5215512345678';
        });

        // Nada debe salir hacia Meta desde este sistema.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
