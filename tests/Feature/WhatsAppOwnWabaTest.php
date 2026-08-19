<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Registrar la WABA propia sin pasar por Embedded Signup.
 *
 * Embedded Signup no ofrece la WABA cuando el portfolio dueño de la app es el
 * mismo —no hay nada que conceder—, así que nuestro propio número solo puede
 * entrar por aquí. Sin esta fila el webhook descarta sus mensajes por venir de
 * una WABA desconocida.
 */
class WhatsAppOwnWabaTest extends TestCase
{
    use RefreshDatabase;

    private function configurar(): void
    {
        config([
            'services.whatsapp.business_account_id' => '2436841820155807',
            'services.whatsapp.token'               => 'token-del-system-user',
            'services.whatsapp.app_secret'          => 'secreto',
        ]);
    }

    private function fakeGraphOk(): void
    {
        Http::fake([
            '*/2436841820155807/phone_numbers*' => Http::response(['data' => [[
                'id'                   => '1230737580126123',
                'display_phone_number' => '+52 1 844 341 0326',
                'verified_name'        => 'LunAvalos',
                'quality_rating'       => 'GREEN',
            ]]]),
            '*/2436841820155807/subscribed_apps' => Http::response(['success' => true]),
            '*/2436841820155807*' => Http::response(['id' => '2436841820155807', 'name' => 'LunAvalos']),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_registra_la_waba_propia_y_suscribe_la_app(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba-propia')->assertSuccessful();

        $cuenta = WhatsAppAccount::where('waba_id', '2436841820155807')->firstOrFail();
        $numero = WhatsAppNumber::where('phone_number_id', '1230737580126123')->firstOrFail();

        $this->assertSame('LunAvalos', $cuenta->name);
        $this->assertSame($cuenta->id, $numero->whatsapp_account_id);
        // Null = número propio. No se inventa un Client para representarnos.
        $this->assertNull($numero->client_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/subscribed_apps') && $r->method() === 'POST');
    }

    public function test_no_guarda_el_token_en_la_fila_y_cae_al_de_configuracion(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba-propia')->assertSuccessful();

        $cuenta = WhatsAppAccount::where('waba_id', '2436841820155807')->firstOrFail();

        // Guardarlo duplicaría el secreto sin ganar nada: es nuestro token, no
        // el de un tercero.
        $this->assertNull($cuenta->getRawOriginal('access_token'));
        $this->assertSame('token-del-system-user', $cuenta->tokenParaEnviar());
    }

    public function test_es_idempotente(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba-propia')->assertSuccessful();
        $this->artisan('whatsapp:adoptar-waba-propia')->assertSuccessful();

        $this->assertSame(1, WhatsAppAccount::count());
        $this->assertSame(1, WhatsAppNumber::count());
    }

    public function test_falla_limpio_si_falta_configuracion(): void
    {
        config(['services.whatsapp.business_account_id' => '', 'services.whatsapp.token' => '']);

        Http::fake();

        $this->artisan('whatsapp:adoptar-waba-propia')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $this->configurar();
        Http::fake();

        $this->artisan('whatsapp:adoptar-waba-propia', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, WhatsAppAccount::count());
        Http::assertNothingSent();
    }

    /**
     * La prueba que justifica todo el comando: con la WABA registrada, un
     * mensaje entrante deja de descartarse y abre conversación.
     */
    public function test_despues_de_registrarla_el_webhook_ya_enruta_los_mensajes(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba-propia')->assertSuccessful();

        $payload = [
            'entry' => [[
                'id'      => '2436841820155807',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '1230737580126123'],
                        'contacts' => [['wa_id' => '5218110000000', 'profile' => ['name' => 'Ana']]],
                        'messages' => [[
                            'id'   => 'wamid.PRUEBA_REAL',
                            'from' => '5218110000000',
                            'type' => 'text',
                            'text' => ['body' => 'Hola'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson(route('whatsapp.webhook.receive'), $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secreto'),
        ])->assertOk();

        $conversacion = Conversation::where('contact_wa_id', '5218110000000')->firstOrFail();

        $this->assertNull($conversacion->client_id);
        $this->assertSame('Hola', $conversacion->messages()->first()->body);
    }
}
