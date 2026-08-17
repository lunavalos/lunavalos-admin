<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La bandeja de Conversaciones. Dos cosas se cuidan aquí por encima del resto:
 *
 *  - El acotado por cliente. Aquí viven mensajes de clientes finales de
 *    terceros; que un cliente vea la conversación de otro es una fuga de datos.
 *  - La ventana de 24 h. Fuera de ella Meta no entrega texto libre, y antes el
 *    mensaje se guardaba como si hubiera salido.
 */
class ConversationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function numero(?Client $client = null): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::firstOrCreate(
            ['waba_id' => '2436841820155807'],
            ['name' => 'LunAvalos'],
        );

        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => $client?->id,
            'phone_number_id'      => (string) fake()->unique()->numerify('##############'),
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    private function cliente(string $nombre): Client
    {
        return Client::create(['business_name' => $nombre]);
    }

    private function conversacion(WhatsAppNumber $numero, ?Client $client = null, bool $ventanaAbierta = true): Conversation
    {
        return Conversation::create([
            'client_id'          => $client?->id,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => fake()->unique()->numerify('52155#######'),
            'contact_name'       => 'Contacto',
            'last_inbound_at'    => $ventanaAbierta ? now()->subHour() : now()->subDays(3),
            'last_message_at'    => now()->subHour(),
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['client_id' => null]);
    }

    // -----------------------------------------------------------------
    // Acceso
    // -----------------------------------------------------------------

    public function test_requiere_sesion(): void
    {
        $this->get(route('conversations.index'))->assertRedirect(route('login'));
    }

    public function test_un_usuario_de_portal_no_ve_la_conversacion_de_otro_cliente(): void
    {
        $unCliente  = $this->cliente('Cliente A');
        $otroCliente = $this->cliente('Cliente B');

        $conversacionAjena = $this->conversacion($this->numero($otroCliente), $otroCliente);

        $usuario = User::factory()->create(['client_id' => $unCliente->id]);

        $this->actingAs($usuario)
            ->get(route('conversations.show', $conversacionAjena))
            ->assertForbidden();
    }

    public function test_la_bandeja_de_un_usuario_de_portal_solo_trae_lo_suyo(): void
    {
        $suyo  = $this->cliente('Cliente A');
        $ajeno = $this->cliente('Cliente B');

        $propia = $this->conversacion($this->numero($suyo), $suyo);
        $this->conversacion($this->numero($ajeno), $ajeno);

        $usuario = User::factory()->create(['client_id' => $suyo->id]);

        $this->actingAs($usuario)
            ->get(route('conversations.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Conversations/Index')
                ->has('conversations', 1)
                ->where('conversations.0.id', $propia->id));
    }

    public function test_abrir_la_conversacion_limpia_el_contador_de_no_leidos(): void
    {
        $conversacion = $this->conversacion($this->numero());
        $conversacion->forceFill(['unread_count' => 4])->save();

        $this->actingAs($this->staff())
            ->get(route('conversations.show', $conversacion))
            ->assertOk();

        $this->assertSame(0, $conversacion->fresh()->unread_count);
    }

    // -----------------------------------------------------------------
    // Responder
    // -----------------------------------------------------------------

    public function test_responder_envia_a_graph_y_guarda_el_mensaje(): void
    {
        config([
            'services.whatsapp.token'         => 'token-de-prueba',
            'services.whatsapp.graph_version' => 'v26.0',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(
            ['messages' => [['id' => 'wamid.SALIDA1']]], 200
        )]);

        $numero       = $this->numero();
        $conversacion = $this->conversacion($numero);

        $this->actingAs($this->staff())
            ->post(route('conversations.reply', $conversacion), ['body' => 'Claro que sí'])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversacion->id,
            'wa_message_id'   => 'wamid.SALIDA1',
            'direction'       => ConversationMessage::DIRECTION_OUT,
            'author_type'     => ConversationMessage::AUTHOR_STAFF,
            'delivery_status' => ConversationMessage::DELIVERY_SENT,
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), $numero->phone_number_id));
    }

    public function test_si_meta_rechaza_el_envio_el_mensaje_queda_marcado_como_fallido(): void
    {
        config(['services.whatsapp.token' => 'token-de-prueba']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 470]], 400)]);

        $conversacion = $this->conversacion($this->numero());

        $this->actingAs($this->staff())
            ->post(route('conversations.reply', $conversacion), ['body' => 'Hola'])
            ->assertRedirect();

        // Guardarlo como enviado sería mentirle al equipo: el contacto no lo recibió.
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversacion->id,
            'wa_message_id'   => null,
            'delivery_status' => ConversationMessage::DELIVERY_FAILED,
        ]);
    }

    public function test_no_deja_responder_con_la_ventana_de_24h_cerrada(): void
    {
        Http::fake();

        $conversacion = $this->conversacion($this->numero(), null, ventanaAbierta: false);

        $this->actingAs($this->staff())
            ->post(route('conversations.reply', $conversacion), ['body' => 'Seguimiento tardío'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('conversation_messages', 0);
        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Escalar a ticket
    // -----------------------------------------------------------------

    public function test_crear_ticket_desde_la_conversacion_lo_deja_enlazado(): void
    {
        $cliente      = $this->cliente('Cliente A');
        $conversacion = $this->conversacion($this->numero($cliente), $cliente);

        $this->actingAs($this->staff())
            ->post(route('conversations.createTicket', $conversacion), ['title' => 'Arreglar la página'])
            ->assertRedirect();

        $ticket = Ticket::firstWhere('conversation_id', $conversacion->id);

        $this->assertNotNull($ticket);
        $this->assertSame('Arreglar la página', $ticket->title);
        $this->assertSame($cliente->id, $ticket->client_id);

        // La conversación NO se cierra al escalar: sigue viva.
        $this->assertSame(Conversation::STATUS_OPEN, $conversacion->fresh()->status);
    }

    public function test_archivar_y_reabrir(): void
    {
        $conversacion = $this->conversacion($this->numero());

        $this->actingAs($this->staff())
            ->post(route('conversations.status', $conversacion), ['status' => Conversation::STATUS_ARCHIVED])
            ->assertRedirect();

        $this->assertSame(Conversation::STATUS_ARCHIVED, $conversacion->fresh()->status);

        // Un entrante siempre reabre: archivar no debe esconder a alguien que volvió.
        $conversacion->fresh()->registrarEntrante();

        $this->assertSame(Conversation::STATUS_OPEN, $conversacion->fresh()->status);
    }
}
