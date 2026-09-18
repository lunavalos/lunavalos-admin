<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Alta de una WABA del portfolio propio asignada a un cliente.
 *
 * Es el camino que Embedded Signup no puede cubrir: Meta pinta nuestro propio
 * portfolio en gris («This Meta Business Account owns the app»), así que una
 * WABA creada dentro de LunAvalos Manager solo puede entrar por aquí.
 *
 * Lo que estos tests protegen sobre todo es el número de prueba que Meta regala
 * a cada WABA nueva: si se le cuelga al cliente, queda con dos números activos
 * y la API de plataforma falla en vez de adivinar desde cuál enviar.
 */
class WhatsAppAdoptWabaTest extends TestCase
{
    use RefreshDatabase;

    private const WABA = '3344556677';
    private const REAL = '1230737580126123';
    private const PRUEBA = '9999999999';

    private function configurar(): void
    {
        config([
            'services.whatsapp.token'      => 'token-del-system-user',
            'services.whatsapp.app_secret' => 'secreto',
        ]);
    }

    /**
     * Una WABA recién creada: el número real del cliente y el +1 555 de Meta.
     */
    private function fakeGraphOk(): void
    {
        Http::fake([
            '*/' . self::WABA . '/phone_numbers*' => Http::response(['data' => [
                [
                    'id'                   => self::REAL,
                    'display_phone_number' => '+52 1 844 341 0326',
                    'verified_name'        => 'Macadam Desarrollos',
                    'quality_rating'       => 'GREEN',
                    'status'               => 'CONNECTED',
                ],
                [
                    'id'                   => self::PRUEBA,
                    'display_phone_number' => '+1 555-363-2653',
                    'verified_name'        => 'Macadam Desarrollos',
                    'status'               => 'CONNECTED',
                ],
            ]]),
            '*/' . self::WABA . '/subscribed_apps' => Http::response(['success' => true]),
            '*/' . self::WABA . '*' => Http::response(['id' => self::WABA, 'name' => 'Macadam Desarrollos']),
            '*' => Http::response([], 200),
        ]);
    }

    private function cliente(): Client
    {
        return Client::create(['business_name' => 'Macadam Desarrollos']);
    }

    public function test_asigna_al_cliente_solo_el_numero_indicado(): void
    {
        $this->configurar();
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
            '--numero'  => [self::REAL],
        ])->assertSuccessful();

        $real   = WhatsAppNumber::where('phone_number_id', self::REAL)->firstOrFail();
        $prueba = WhatsAppNumber::where('phone_number_id', self::PRUEBA)->firstOrFail();

        $this->assertSame($cliente->id, $real->client_id);
        $this->assertTrue($real->is_active);

        // El de prueba existe —el webhook tiene que poder enrutarlo— pero no es
        // de nadie y no se ofrece para enviar.
        $this->assertNull($prueba->client_id);
        $this->assertFalse($prueba->is_active);
    }

    public function test_el_numero_de_prueba_no_deja_al_cliente_con_dos_activos(): void
    {
        $this->configurar();
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
            '--numero'  => [self::REAL],
        ])->assertSuccessful();

        // Es la condición exacta que hace fallar a ApiController::numeroDeEnvio().
        $this->assertSame(1, WhatsAppNumber::where('client_id', $cliente->id)
            ->where('is_active', true)
            ->count());
    }

    public function test_guarda_la_waba_sin_token_propio_y_cae_al_del_system_user(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $this->cliente()->id,
            '--numero'  => [self::REAL],
        ])->assertSuccessful();

        $cuenta = WhatsAppAccount::where('waba_id', self::WABA)->firstOrFail();

        // Guardarlo duplicaría el secreto sin ganar nada: la WABA es nuestra.
        $this->assertNull($cuenta->access_token);
        $this->assertSame('token-del-system-user', $cuenta->tokenParaEnviar());
    }

    public function test_es_idempotente_y_no_reactiva_el_numero_de_prueba(): void
    {
        $this->configurar();
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $argumentos = [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
            '--numero'  => [self::REAL],
        ];

        $this->artisan('whatsapp:adoptar-waba', $argumentos)->assertSuccessful();
        $this->artisan('whatsapp:adoptar-waba', $argumentos)->assertSuccessful();

        $this->assertSame(1, WhatsAppAccount::where('waba_id', self::WABA)->count());
        $this->assertSame(2, WhatsAppNumber::count());
        $this->assertFalse(
            WhatsAppNumber::where('phone_number_id', self::PRUEBA)->firstOrFail()->is_active
        );
    }

    public function test_no_desasigna_un_numero_que_ya_era_de_otro_cliente(): void
    {
        $this->configurar();
        $this->fakeGraphOk();
        $cliente = $this->cliente();
        $otro    = Client::create(['business_name' => 'Otro']);

        // Alguien asignó a mano el de prueba antes. Volver a adoptar no debe
        // tumbarle el canal en silencio.
        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
            '--numero'  => [self::REAL],
        ])->assertSuccessful();

        WhatsAppNumber::where('phone_number_id', self::PRUEBA)
            ->update(['client_id' => $otro->id, 'is_active' => true]);

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
            '--numero'  => [self::REAL],
        ])->assertSuccessful();

        $prueba = WhatsAppNumber::where('phone_number_id', self::PRUEBA)->firstOrFail();

        $this->assertSame($otro->id, $prueba->client_id);
        $this->assertTrue($prueba->is_active);
    }

    public function test_rechaza_un_numero_que_no_esta_en_la_waba(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $this->cliente()->id,
            '--numero'  => ['0000000000'],
        ])->assertFailed();

        $this->assertSame(0, WhatsAppAccount::count());
    }

    public function test_sin_numero_y_sin_interaccion_no_asigna_a_ciegas(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        // Asignar todos le colaría el número de prueba al cliente, así que se
        // niega en vez de adivinar. Importa en cron y en despliegues, donde no
        // hay nadie a quien preguntarle.
        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'          => self::WABA,
            '--cliente'        => $this->cliente()->id,
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertSame(0, WhatsAppAccount::count());
    }

    public function test_sin_numero_pregunta_cual_es_del_cliente(): void
    {
        $this->configurar();
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        // `choice` sobre un array asociativo devuelve la clave, o sea el
        // phone_number_id. Es el camino que recorre quien lo corre a mano.
        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $cliente->id,
        ])
            ->expectsQuestion("¿Qué números son de {$cliente->business_name}?", [self::REAL])
            ->assertSuccessful();

        $this->assertSame(
            $cliente->id,
            WhatsAppNumber::where('phone_number_id', self::REAL)->firstOrFail()->client_id,
        );
        $this->assertNull(
            WhatsAppNumber::where('phone_number_id', self::PRUEBA)->firstOrFail()->client_id,
        );
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $this->configurar();
        $this->fakeGraphOk();

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $this->cliente()->id,
            '--numero'  => [self::REAL],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, WhatsAppAccount::count());
        $this->assertSame(0, WhatsAppNumber::count());
    }

    public function test_falla_limpio_si_meta_no_da_acceso_a_la_waba(): void
    {
        $this->configurar();

        Http::fake([
            '*/phone_numbers*' => Http::response([
                'error' => ['code' => 200, 'message' => 'Permissions error'],
            ], 403),
            '*' => Http::response([], 200),
        ]);

        $this->artisan('whatsapp:adoptar-waba', [
            'waba_id'   => self::WABA,
            '--cliente' => $this->cliente()->id,
            '--numero'  => [self::REAL],
        ])->assertFailed();

        $this->assertSame(0, WhatsAppAccount::count());
    }
}
