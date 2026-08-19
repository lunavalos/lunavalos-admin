<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Database\Seeders\PlatformReviewerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cuenta que se le entrega a los revisores de Meta.
 *
 * Lo que se cuida aquí es que el revisor pueda demostrar los dos permisos de
 * WhatsApp que se están solicitando —si entra y las pantallas están vacías o
 * le dan 403, la ronda se rechaza— y que los datos de demo no toquen nada de
 * producción.
 */
class PlatformReviewerSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): User
    {
        putenv('REVIEWER_PASSWORD=contrasena-de-prueba');

        $this->seed(PlatformReviewerSeeder::class);

        return User::where('email', 'platform-reviewer@lunavalos.com')->firstOrFail();
    }

    public function test_el_revisor_puede_evaluar_los_dos_permisos_de_whatsapp(): void
    {
        $revisor = $this->sembrar();

        // Sin estos, entra y no hay nada que evaluar.
        $this->assertTrue($revisor->can('Ver Conversaciones'));
        $this->assertTrue($revisor->can('Responder Conversaciones'));
        $this->assertTrue($revisor->can('Gestionar Plantillas WhatsApp'));
        $this->assertTrue($revisor->can('Gestionar WhatsApp'));
    }

    public function test_hay_una_conversacion_demo_con_la_ventana_abierta(): void
    {
        $revisor = $this->sembrar();

        $conversacion = Conversation::where('contact_wa_id', '5215500000001')->firstOrFail();

        $this->assertSame($revisor->client_id, $conversacion->client_id);
        $this->assertSame(2, $conversacion->messages()->count());
        // Con la ventana cerrada el revisor vería el caso excepcional —texto
        // libre bloqueado— en vez del flujo normal.
        $this->assertTrue($conversacion->ventanaAbierta());
    }

    public function test_hay_una_plantilla_aprobada_para_la_pantalla_de_plantillas(): void
    {
        $this->sembrar();

        $plantilla = WhatsAppTemplate::where('name', 'delivery_confirmation')->firstOrFail();

        $this->assertTrue($plantilla->estaAprobada());
        $this->assertSame(2, $plantilla->body_variables);
    }

    public function test_la_cuenta_demo_no_hereda_el_token_de_produccion(): void
    {
        config(['services.whatsapp.token' => 'TOKEN-REAL-DE-PRODUCCION']);

        $this->sembrar();

        $cuenta = WhatsAppAccount::where('waba_id', 'DEMO-WABA-0001')->firstOrFail();

        // tokenParaEnviar() cae a la configuración cuando la columna está
        // vacía: si el fixture dejara el token nulo, un intento de respuesta
        // del revisor saldría con nuestras credenciales reales.
        $this->assertNotSame('TOKEN-REAL-DE-PRODUCCION', $cuenta->tokenParaEnviar());
    }

    public function test_el_revisor_ve_su_conversacion_demo_y_ninguna_otra(): void
    {
        $revisor = $this->sembrar();

        $this->actingAs($revisor)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Conversations/Index')
                ->has('conversations', 1));

        $this->assertSame(
            1,
            ConversationMessage::whereIn('wa_message_id', ['wamid.DEMO_CONV_OUT_1'])->count(),
        );
    }
}
