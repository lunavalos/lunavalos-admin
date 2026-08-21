<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Asignar un número de nuestra WABA a un cliente.
 *
 * Es el modelo de §4: con Standard Access, la WABA de LunAvalos puede alojar
 * números de varios clientes, y es el **número** —no la WABA— lo que determina
 * de quién es cada conversación.
 *
 * Lo que se cuida:
 *
 *  1. **Que re-adoptar la WABA no desasigne lo asignado a mano.** Era el bug:
 *     `sincronizarNumeros()` escribía `client_id => null` en cada corrida, así
 *     que volver a correr `whatsapp:adoptar-waba-propia` devolvía el número de
 *     un cliente a "propio de LunAvalos" **en silencio**. El síntoma habría
 *     aparecido días después como conversaciones que el cliente dejó de ver y
 *     un agente de IA respondiendo con el prompt equivocado.
 *  2. Que Embedded Signup sí mande sobre el dueño: ahí la WABA es del cliente.
 */
class WhatsAppNumberAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const WABA = '2436841820155807';
    private const PNID = '1230737580126123';

    private function configurar(): void
    {
        config([
            'services.whatsapp.business_account_id' => self::WABA,
            'services.whatsapp.token'               => 'token-system-user',
            'services.whatsapp.graph_version'       => 'v26.0',
        ]);
    }

    private function fingirMeta(): void
    {
        Http::fake([
            "*/{$this->wabaPath()}/phone_numbers*" => Http::response(['data' => [[
                'id'                   => self::PNID,
                'display_phone_number' => '+52 1 844 341 0326',
                'verified_name'        => 'LunAvalos',
                'quality_rating'       => 'GREEN',
            ]]]),
            '*/subscribed_apps' => Http::response(['success' => true]),
            "*/{$this->wabaPath()}*" => Http::response(['id' => self::WABA, 'name' => 'LunAvalos']),
        ]);
    }

    private function wabaPath(): string
    {
        return self::WABA;
    }

    public function test_readoptar_la_waba_no_desasigna_un_numero_de_su_cliente(): void
    {
        $this->configurar();
        $this->fingirMeta();

        $servicio = app(WhatsAppOnboardingService::class);

        // Primera adopción: el número nace como propio.
        $servicio->adoptarWabaPropia();

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PNID);
        $this->assertNull($numero->client_id);

        // Se le asigna a Macadam, que es el modelo de WABA compartida.
        $macadam = Client::create(['business_name' => 'Constructora MACADAM']);
        $numero->update(['client_id' => $macadam->id]);

        // Alguien vuelve a correr el comando —es idempotente, se supone seguro—.
        $servicio->adoptarWabaPropia();

        $this->assertSame(
            $macadam->id,
            $numero->fresh()->client_id,
            'Re-adoptar la WABA desasignó el número de su cliente.',
        );
    }

    public function test_readoptar_si_refresca_los_datos_del_numero(): void
    {
        $this->configurar();
        $this->fingirMeta();

        $servicio = app(WhatsAppOnboardingService::class);
        $servicio->adoptarWabaPropia();

        $numero = WhatsAppNumber::firstWhere('phone_number_id', self::PNID);
        $numero->update(['quality_rating' => 'RED', 'is_active' => false]);

        $servicio->adoptarWabaPropia();

        // El dueño se respeta, pero lo que sí viene de Meta se actualiza.
        $this->assertSame('GREEN', $numero->fresh()->quality_rating);
        $this->assertTrue($numero->fresh()->is_active);
    }

    public function test_el_comando_asigna_y_migra_las_conversaciones(): void
    {
        $cuenta = WhatsAppAccount::create([
            'waba_id' => self::WABA, 'name' => 'LunAvalos', 'access_token' => 't',
        ]);

        $numero = WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => null,
            'phone_number_id'      => self::PNID,
            'display_phone_number' => '+52 1 844 341 0326',
        ]);

        // Una conversación que ya existía cuando el número era "propio".
        $conv = Conversation::create([
            'client_id'          => null,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5210000000001',
        ]);

        $macadam = Client::create(['business_name' => 'Constructora MACADAM']);

        $this->artisan('whatsapp:asignar-numero', [
            'numero'   => self::PNID,
            'cliente'  => $macadam->id,
            '--migrar' => true,
        ])->assertSuccessful();

        $this->assertSame($macadam->id, $numero->fresh()->client_id);
        // Sin migrar, el cliente no vería su propio historial.
        $this->assertSame($macadam->id, $conv->fresh()->client_id);
    }

    public function test_sin_migrar_avisa_de_las_conversaciones_que_quedan_atras(): void
    {
        $cuenta = WhatsAppAccount::create([
            'waba_id' => self::WABA, 'name' => 'LunAvalos', 'access_token' => 't',
        ]);

        $numero = WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'phone_number_id'      => self::PNID,
            'display_phone_number' => '+52 1 844 341 0326',
        ]);

        Conversation::create([
            'client_id'          => null,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5210000000002',
        ]);

        $macadam = Client::create(['business_name' => 'Macadam']);

        $this->artisan('whatsapp:asignar-numero', [
            'numero'  => self::PNID,
            'cliente' => $macadam->id,
        ])
            ->expectsOutputToContain('siguen con el dueño anterior')
            ->assertSuccessful();

        $this->assertNull(Conversation::first()->client_id);
    }

    public function test_un_numero_inexistente_no_revienta(): void
    {
        $this->artisan('whatsapp:asignar-numero', ['numero' => '000', 'cliente' => null])
            ->assertFailed();
    }
}
