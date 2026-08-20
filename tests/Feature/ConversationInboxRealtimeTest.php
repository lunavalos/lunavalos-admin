<?php

namespace Tests\Feature;

use App\Broadcasting\ClientInboxChannel;
use App\Broadcasting\InternalInboxChannel;
use App\Events\ConversationUpdated;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El tiempo real de la BANDEJA, no del hilo.
 *
 * Lo que se cuida aquí es el acotamiento: por estos canales viajan el nombre y
 * el teléfono de clientes finales de terceros, así que un usuario de portal no
 * puede poder escuchar el canal de otro cliente ni el de los números propios.
 */
class ConversationInboxRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function numero(?Client $client = null): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::firstOrCreate(
            ['waba_id' => '2436841820155807'],
            ['name' => 'LunAvalos', 'status' => WhatsAppAccount::STATUS_ACTIVE],
        );

        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => $client?->id,
            'phone_number_id'      => (string) fake()->unique()->numerify('##############'),
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    private function conversacion(?Client $client = null): Conversation
    {
        return Conversation::create([
            'client_id'          => $client?->id,
            'whatsapp_number_id' => $this->numero($client)->id,
            'contact_wa_id'      => (string) fake()->unique()->numerify('52155#######'),
            'last_inbound_at'    => now()->subHour(),
            'last_message_at'    => now()->subHour(),
        ]);
    }

    // -----------------------------------------------------------------
    // Quién puede escuchar
    //
    // Se prueban las clases de canal directamente y no /broadcasting/auth: en
    // los tests el driver es `null`, que autoriza cualquier canal sin mirar la
    // regla, así que por ahí todo pasaría siempre.
    // -----------------------------------------------------------------

    public function test_un_usuario_de_portal_no_escucha_la_bandeja_de_otro_cliente(): void
    {
        $suyo  = Client::create(['business_name' => 'Cliente A']);
        $ajeno = Client::create(['business_name' => 'Cliente B']);

        $usuario = User::factory()->create(['client_id' => $suyo->id]);
        $canal   = new ClientInboxChannel();

        $this->assertTrue($canal->join($usuario, $suyo->id));
        $this->assertFalse($canal->join($usuario, $ajeno->id));
    }

    public function test_un_usuario_de_portal_no_escucha_el_canal_interno(): void
    {
        $suyo = Client::create(['business_name' => 'Cliente A']);

        // El canal interno lleva TODAS las conversaciones, incluidas las de los
        // números propios y las del resto de clientes.
        $this->assertFalse(
            (new InternalInboxChannel())->join(
                User::factory()->create(['client_id' => $suyo->id])
            )
        );
    }

    public function test_el_staff_interno_escucha_los_dos_canales(): void
    {
        $interno = User::factory()->create(['client_id' => null]);
        $cliente = Client::create(['business_name' => 'Cliente A']);

        $this->assertTrue((new InternalInboxChannel())->join($interno));
        $this->assertTrue((new ClientInboxChannel())->join($interno, $cliente->id));
    }

    // -----------------------------------------------------------------
    // Cuándo se emite
    // -----------------------------------------------------------------

    public function test_un_mensaje_entrante_actualiza_la_bandeja(): void
    {
        config(['services.whatsapp.app_secret' => 'secreto']);
        Event::fake([ConversationUpdated::class]);

        $cliente = Client::create(['business_name' => 'Macadam']);
        $numero  = $this->numero($cliente);

        $payload = [
            'entry' => [[
                'id'      => '2436841820155807',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $numero->phone_number_id],
                        'contacts' => [['wa_id' => '5218110000000', 'profile' => ['name' => 'Ana']]],
                        'messages' => [[
                            'id' => 'wamid.BANDEJA', 'from' => '5218110000000',
                            'type' => 'text', 'text' => ['body' => 'Hola'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson(route('whatsapp.webhook.receive'), $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secreto'),
        ])->assertOk();

        Event::assertDispatched(ConversationUpdated::class);
    }

    public function test_responder_tambien_actualiza_la_bandeja_del_resto_del_equipo(): void
    {
        Event::fake([ConversationUpdated::class]);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

        $conversacion = $this->conversacion();

        $staff = User::factory()->create(['client_id' => null]);
        $staff->givePermissionTo(Permission::findOrCreate('Responder Conversaciones', 'web'));

        $this->actingAs($staff)
            ->post(route('conversations.reply', $conversacion), ['body' => 'Hola'])
            ->assertRedirect();

        // El contador vuelve a cero y la conversación sube al principio: si la
        // bandeja de los demás no se entera, siguen viendo un no leído que ya
        // atendió otro.
        Event::assertDispatched(ConversationUpdated::class);
    }

    public function test_una_conversacion_de_numero_propio_no_va_a_ningun_canal_de_cliente(): void
    {
        // client_id null = número propio de LunAvalos. No hay cliente a cuyo
        // canal mandarla, y no debe inventarse uno.
        $canales = (new ConversationUpdated($this->conversacion()))->broadcastOn();

        $this->assertCount(1, $canales);
        $this->assertSame('private-conversations.internal', $canales[0]->name);
    }

    public function test_la_conversacion_de_un_cliente_va_a_los_dos_canales(): void
    {
        $cliente = Client::create(['business_name' => 'Macadam']);

        $canales = collect((new ConversationUpdated($this->conversacion($cliente)))->broadcastOn())
            ->map(fn ($c) => $c->name);

        $this->assertContains('private-conversations.internal', $canales->all());
        $this->assertContains("private-conversations.client.{$cliente->id}", $canales->all());
    }
}
