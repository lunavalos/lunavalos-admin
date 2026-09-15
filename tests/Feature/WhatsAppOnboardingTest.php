<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Embedded Signup: el único camino que Meta acepta para onboardear la WABA de
 * un cliente.
 *
 * Lo que más se cuida aquí es la idempotencia. El cliente va a repetir el flujo
 * —al reconectar tras revocar, o por error— y repetirlo no debe duplicar
 * números ni desligar las conversaciones que ya cuelgan de ellos.
 */
class WhatsAppOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private const WABA_ID  = '9988776655';
    private const PHONE_ID = '1122334455';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.app_id'                   => '1531774538464754',
            'services.whatsapp.app_secret'               => 'app-secret',
            'services.whatsapp.graph_version'            => 'v26.0',
            'services.whatsapp.embedded_signup_config_id' => 'config-123',
        ]);
    }

    private function cliente(): Client
    {
        return Client::create(['business_name' => 'Grupo Macadam']);
    }

    private function staff(): User
    {
        return $this->conPermiso(User::factory()->create(['client_id' => null]));
    }

    /**
     * El módulo va cerrado por `Gestionar WhatsApp`. Los usuarios de las
     * pruebas de scoping también lo llevan: así el 403 que se comprueba viene
     * del cliente equivocado y no del permiso, que es lo que se quiere probar.
     */
    private function conPermiso(User $usuario): User
    {
        $usuario->givePermissionTo(Permission::findOrCreate('Gestionar WhatsApp', 'web'));

        return $usuario;
    }

    /**
     * Las cuatro llamadas del flujo. El comodín final es obligatorio, no
     * cosmético: Http::fake() con un arreglo de patrones deja salir a la red
     * real lo que no coincide con ninguno.
     */
    private function fakeGraphOk(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'token-del-cliente'], 200),
            '*/phone_numbers*'      => Http::response(['data' => [[
                'id'                   => self::PHONE_ID,
                'display_phone_number' => '+52 1 844 000 1111',
                'verified_name'        => 'Grupo Macadam',
                'quality_rating'       => 'GREEN',
            ]]], 200),
            '*/subscribed_apps*'    => Http::response(['success' => true], 200),
            '*'                     => Http::response(['id' => self::WABA_ID, 'name' => 'Macadam WABA'], 200),
        ]);
    }

    public function test_conecta_la_waba_los_numeros_y_suscribe_la_app(): void
    {
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $cliente), [
                'code'    => 'code-de-corta-vida',
                'waba_id' => self::WABA_ID,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cuenta = WhatsAppAccount::firstWhere('waba_id', self::WABA_ID);
        $this->assertNotNull($cuenta);
        $this->assertSame('Macadam WABA', $cuenta->name);
        $this->assertSame(WhatsAppAccount::STATUS_ACTIVE, $cuenta->status);
        // El token del cliente se guarda cifrado, no en claro.
        $this->assertSame('token-del-cliente', $cuenta->access_token);

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PHONE_ID);
        $this->assertNotNull($numero);
        $this->assertSame($cliente->id, $numero->client_id);
        $this->assertSame('GREEN', $numero->quality_rating);

        // Sin esta llamada no llega un solo mensaje de esa WABA.
        Http::assertSent(fn ($r) => str_contains($r->url(), self::WABA_ID . '/subscribed_apps')
            && $r->method() === 'POST');
    }

    public function test_el_token_queda_cifrado_en_la_base(): void
    {
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $cliente), [
                'code'    => 'code',
                'waba_id' => self::WABA_ID,
            ]);

        // Es credencial de un tercero: no basta con ocultarla de las respuestas.
        $crudo = \DB::table('whatsapp_accounts')->where('waba_id', self::WABA_ID)->value('access_token');

        $this->assertNotSame('token-del-cliente', $crudo);
        $this->assertStringNotContainsString('token-del-cliente', (string) $crudo);
    }

    public function test_reconectar_no_duplica_numeros(): void
    {
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->staff())
                ->post(route('whatsapp.connect.store', $cliente), [
                    'code'    => 'code',
                    'waba_id' => self::WABA_ID,
                ]);
        }

        $this->assertSame(1, WhatsAppAccount::count());
        $this->assertSame(1, WhatsAppNumber::count());
    }

    public function test_si_meta_rechaza_el_canje_no_se_guarda_nada(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response([
                'error' => ['message' => 'This authorization code has expired.', 'code' => 100],
            ], 400),
            '*' => Http::response([], 200),
        ]);

        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $cliente), ['code' => 'code-vencido'])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, WhatsAppAccount::count());
        $this->assertSame(0, WhatsAppNumber::count());
    }

    public function test_desconectar_revoca_el_token_y_apaga_los_numeros(): void
    {
        $this->fakeGraphOk();
        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $cliente), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ]);

        $cuenta = WhatsAppAccount::firstWhere('waba_id', self::WABA_ID);

        $this->actingAs($this->staff())
            ->delete(route('whatsapp.connect.destroy', [$cliente, $cuenta]))
            ->assertRedirect();

        $cuenta->refresh();
        $this->assertSame(WhatsAppAccount::STATUS_REVOKED, $cuenta->status);
        $this->assertNull($cuenta->access_token);
        $this->assertFalse((bool) $cuenta->numbers()->first()->is_active);
    }

    public function test_no_se_puede_desconectar_la_waba_de_otro_cliente(): void
    {
        $this->fakeGraphOk();
        $mio   = $this->cliente();
        $ajeno = Client::create(['business_name' => 'Otro Cliente']);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $mio), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ]);

        $cuenta = WhatsAppAccount::firstWhere('waba_id', self::WABA_ID);

        // Un id en la URL no debe alcanzar para desconectar lo de otro.
        $this->actingAs($this->staff())
            ->delete(route('whatsapp.connect.destroy', [$ajeno, $cuenta]))
            ->assertForbidden();

        $this->assertSame(WhatsAppAccount::STATUS_ACTIVE, $cuenta->fresh()->status);
    }

    public function test_sin_el_permiso_no_se_puede_conectar_ni_desconectar(): void
    {
        $cliente = $this->cliente();

        Http::fake();

        // Usuario interno: client_id null, así que el scoping por cliente lo
        // deja pasar. Antes de este gate bastaba con escribir la URL.
        $sinPermiso = User::factory()->create(['client_id' => null]);

        $this->actingAs($sinPermiso)
            ->get(route('whatsapp.connect.show', $cliente))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->post(route('whatsapp.connect.store', $cliente), ['code' => 'code-de-prueba'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_un_usuario_de_portal_no_conecta_para_otro_cliente(): void
    {
        $suyo  = $this->cliente();
        $ajeno = Client::create(['business_name' => 'Otro Cliente']);

        $usuario = $this->conPermiso(User::factory()->create(['client_id' => $suyo->id]));

        $this->actingAs($usuario)
            ->get(route('whatsapp.connect.show', $ajeno))
            ->assertForbidden();
    }

    public function test_la_pantalla_avisa_si_falta_el_config_id(): void
    {
        config(['services.whatsapp.embedded_signup_config_id' => null]);

        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->get(route('whatsapp.connect.show', $cliente))
            ->assertInertia(fn ($page) => $page
                ->component('WhatsApp/Connect')
                ->where('configId', null));
    }

    /**
     * El paso que faltaba: sin `/register` la WABA queda concedida, el webhook
     * suscrito y el número guardado, pero cualquier envío falla porque para
     * Meta ese número todavía no vive en Cloud API.
     */
    public function test_registra_el_numero_en_cloud_api(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'token-del-cliente'], 200),
            '*/phone_numbers*'      => Http::response(['data' => [[
                'id'                   => self::PHONE_ID,
                'display_phone_number' => '+52 1 844 000 1111',
                'status'               => 'PENDING',
            ]]], 200),
            '*/register'         => Http::response(['success' => true], 200),
            '*/subscribed_apps*' => Http::response(['success' => true], 200),
            '*'                  => Http::response(['id' => self::WABA_ID, 'name' => 'Macadam WABA'], 200),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $this->cliente()), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_contains($r->url(), self::PHONE_ID . '/register')
            && $r->method() === 'POST'
            && $r['messaging_product'] === 'whatsapp'
            && preg_match('/^\d{6}$/', (string) $r['pin']) === 1);

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PHONE_ID);
        $this->assertSame(WhatsAppNumber::ESTADO_CONECTADO, $numero->status);
        $this->assertNotNull($numero->registered_at);
        $this->assertNull($numero->registration_error);
        $this->assertFalse($numero->necesitaRegistro());
    }

    /**
     * El PIN es la credencial de verificación en dos pasos del número del
     * cliente: mismo criterio que el token de la cuenta.
     */
    public function test_el_pin_queda_cifrado_en_la_base(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'token-del-cliente'], 200),
            '*/phone_numbers*'      => Http::response(['data' => [[
                'id'                   => self::PHONE_ID,
                'display_phone_number' => '+52 1 844 000 1111',
                'status'               => 'PENDING',
            ]]], 200),
            '*/register'         => Http::response(['success' => true], 200),
            '*/subscribed_apps*' => Http::response(['success' => true], 200),
            '*'                  => Http::response(['id' => self::WABA_ID], 200),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $this->cliente()), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ]);

        $pin = WhatsAppNumber::firstWhere('phone_number_id', self::PHONE_ID)->registration_pin;
        $crudo = \DB::table('whatsapp_numbers')->where('phone_number_id', self::PHONE_ID)->value('registration_pin');

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $pin);
        $this->assertNotSame($pin, $crudo);
        $this->assertStringNotContainsString((string) $pin, (string) $crudo);
    }

    /**
     * Un número que ya está vivo no se vuelve a registrar: si el cliente fijó
     * su propio PIN, reintentarlo produce un 133005 en cada sincronización.
     */
    public function test_no_reregistra_un_numero_ya_conectado(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'token-del-cliente'], 200),
            '*/phone_numbers*'      => Http::response(['data' => [[
                'id'                   => self::PHONE_ID,
                'display_phone_number' => '+52 1 844 000 1111',
                'status'               => 'CONNECTED',
            ]]], 200),
            '*/subscribed_apps*' => Http::response(['success' => true], 200),
            '*'                  => Http::response(['id' => self::WABA_ID], 200),
        ]);

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $this->cliente()), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ]);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/register'));

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PHONE_ID);
        $this->assertNotNull($numero->registered_at);
        $this->assertFalse($numero->necesitaRegistro());
    }

    /**
     * Que el registro falle no puede dejar al cliente sin entrada de mensajes:
     * para cuando corre, la cuenta y el webhook ya están guardados. Pero
     * tampoco puede quedar invisible, que es el patrón que este módulo ya pagó
     * caro con los envíos fuera de la ventana de 24h.
     */
    public function test_un_fallo_de_registro_no_tumba_la_conexion_pero_queda_a_la_vista(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'token-del-cliente'], 200),
            '*/phone_numbers*'      => Http::response(['data' => [[
                'id'                   => self::PHONE_ID,
                'display_phone_number' => '+52 1 844 000 1111',
                'status'               => 'PENDING',
            ]]], 200),
            '*/register' => Http::response([
                'error' => ['message' => 'Two step verification PIN mismatch', 'code' => 133005],
            ], 400),
            '*/subscribed_apps*' => Http::response(['success' => true], 200),
            '*'                  => Http::response(['id' => self::WABA_ID], 200),
        ]);

        $cliente = $this->cliente();

        $this->actingAs($this->staff())
            ->post(route('whatsapp.connect.store', $cliente), [
                'code' => 'code', 'waba_id' => self::WABA_ID,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // La entrada de mensajes sí quedó montada.
        $this->assertNotNull(WhatsAppAccount::firstWhere('waba_id', self::WABA_ID));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/subscribed_apps') && $r->method() === 'POST');

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PHONE_ID);
        $this->assertTrue($numero->necesitaRegistro());
        $this->assertNull($numero->registered_at);
        // El mensaje dice qué tiene que hacer el cliente, no solo qué dijo Meta.
        $this->assertStringContainsString('verificación en dos pasos', $numero->registration_error);

        // Y la pantalla lo muestra: sin esto el número se ve idéntico a uno sano.
        $this->actingAs($this->staff())
            ->get(route('whatsapp.connect.show', $cliente))
            ->assertInertia(fn ($page) => $page
                ->component('WhatsApp/Connect')
                ->where('numeros.0.necesita_registro', true)
                ->where('numeros.0.registration_error', $numero->registration_error));
    }
}
