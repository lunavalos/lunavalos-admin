<?php

namespace Tests\Feature;

use App\Jobs\NotifyTicketUpdate;
use App\Models\Client;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Aviso interno por WhatsApp cuando cambia un ticket.
 *
 * El caso que lo motivó: que al mover una tarjeta del Kanban llegue un mensaje
 * al teléfono de quien lleva la operación, sin tener que mirar el panel.
 */
class TicketWhatsAppAlertTest extends TestCase
{
    use RefreshDatabase;

    private const DESTINO  = '528442751165';
    private const PHONE_ID = '1230737580126123';
    private const WABA_ID  = '2436841820155807';

    private function encender(string $plantilla = 'ticket_actualizado'): void
    {
        config([
            'services.whatsapp.ticket_alerts.to'       => self::DESTINO,
            'services.whatsapp.ticket_alerts.template' => $plantilla,
            // El número propio no guarda token en su fila: cae al del system
            // user que vive en configuración.
            'services.whatsapp.token'                  => 'token-del-system-user',
            // Y configuración es la que declara cuál es nuestro número, no el
            // orden de los ids en la tabla.
            'services.whatsapp.phone_number_id'        => self::PHONE_ID,
            'services.whatsapp.business_account_id'    => self::WABA_ID,
        ]);
    }

    /**
     * El número propio de LunAvalos: `client_id` null, no un Client inventado
     * para representarnos.
     */
    private function numeroPropio(): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::create([
            'waba_id' => self::WABA_ID,
            'name'    => 'LunAvalos',
            'status'  => WhatsAppAccount::STATUS_ACTIVE,
        ]);

        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => null,
            'phone_number_id'      => self::PHONE_ID,
            'display_phone_number' => '+52 1 844 341 0326',
            'is_active'            => true,
            'status'               => WhatsAppNumber::ESTADO_CONECTADO,
        ]);
    }

    private function plantilla(WhatsAppNumber $numero, string $estado = WhatsAppTemplate::STATUS_APPROVED): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'whatsapp_account_id' => $numero->whatsapp_account_id,
            'meta_id'             => '111222333',
            'name'                => 'ticket_actualizado',
            'language'            => 'es_MX',
            'category'            => 'UTILITY',
            'status'              => $estado,
            'components'          => [[
                'type' => 'BODY',
                'text' => 'Ticket {{1}} — {{2}}. {{3}}. Por {{4}}.',
            ]],
            'body_variables'      => 4,
        ]);
    }

    private function ticket(): Ticket
    {
        return Ticket::create([
            'title'       => 'Banner de septiembre',
            'content'     => 'x',
            'status'      => 'Nuevos',
            'priority'    => 'Media',
            'source_type' => Ticket::SOURCE_SUPPORT,
            'channel'     => Ticket::CHANNEL_WEB,
            'client_id'   => Client::create(['business_name' => 'Grupo Macadam'])->id,
            'creator_id'  => User::factory()->create()->id,
        ]);
    }

    public function test_un_cambio_de_estado_manda_la_plantilla_al_numero_configurado(): void
    {
        $this->encender();
        $numero = $this->numeroPropio();
        $this->plantilla($numero);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.AVISO']]], 200)]);

        $ticket = $this->ticket();
        $ticket->update(['status' => 'En progreso']);

        $mensaje = ConversationMessage::latest('id')->first();
        $this->assertNotNull($mensaje);
        $this->assertSame(self::DESTINO, $mensaje->conversation->contact_wa_id);
        $this->assertSame('template', $mensaje->type);
        $this->assertSame(ConversationMessage::DELIVERY_SENT, $mensaje->delivery_status);
        // En el hilo tiene que leerse el texto real, no el nombre de la plantilla.
        $this->assertStringContainsString('Banner de septiembre', $mensaje->body);
        $this->assertStringContainsString('Pasó de Nuevos a En progreso', $mensaje->body);

        // Y que se note que no lo escribió una persona.
        $this->assertSame(ConversationMessage::AUTHOR_SYSTEM, $mensaje->author_type);

        Http::assertSent(fn ($r) => str_contains($r->url(), self::PHONE_ID . '/messages')
            && $r['type'] === 'template'
            && $r['to'] === self::DESTINO);
    }

    public function test_tambien_avisa_al_cambiar_de_responsable(): void
    {
        $this->encender();
        $numero = $this->numeroPropio();
        $this->plantilla($numero);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.AVISO']]], 200)]);

        $usuario = User::factory()->create(['name' => 'Luna']);
        $this->ticket()->update(['assigned_id' => $usuario->id]);

        $this->assertStringContainsString('Asignado a Luna', ConversationMessage::latest('id')->first()->body);
    }

    /**
     * Notificar cualquier `updated` convertiría el aviso en ruido, y en
     * WhatsApp el ruido no se ignora: se silencia el número entero.
     */
    public function test_no_avisa_por_cambios_que_no_son_estado_ni_responsable(): void
    {
        $this->encender();
        Bus::fake();

        $this->ticket()->update(['title' => 'Otro título', 'priority' => 'Alta']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    public function test_apagado_por_defecto_no_encola_nada(): void
    {
        Bus::fake();

        $this->ticket()->update(['status' => 'En progreso']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    /**
     * Un aviso lo iniciamos nosotros y casi siempre fuera de la ventana de
     * 24 h, así que sin plantilla aprobada Meta responde 131047. Que falle no
     * puede impedir que el ticket se guarde.
     */
    public function test_una_plantilla_sin_aprobar_no_rompe_el_cambio_de_ticket(): void
    {
        $this->encender();
        $numero = $this->numeroPropio();
        $this->plantilla($numero, WhatsAppTemplate::STATUS_PENDING);

        Http::fake();

        $ticket = $this->ticket();
        $ticket->update(['status' => 'En progreso']);

        // El ticket sí cambió.
        $this->assertSame('En progreso', $ticket->fresh()->status);
        // Y no se inventó un mensaje que nunca salió.
        $this->assertSame(0, ConversationMessage::count());
        Http::assertNothingSent();
    }

    public function test_sin_numero_propio_no_revienta(): void
    {
        $this->encender();
        Http::fake();

        $ticket = $this->ticket();
        $ticket->update(['status' => 'Completados']);

        $this->assertSame('Completados', $ticket->fresh()->status);
        Http::assertNothingSent();
    }

    /**
     * Producción arrastra la WABA de prueba que Meta regala: registrada el
     * 2026-08-19, nunca borrada, y su número también tiene `client_id` null
     * con un id MÁS BAJO que el de la cuenta real.
     *
     * Elegir el número propio con `first()` cogía ése, y entonces la plantilla
     * —que vive en la WABA de verdad— "no existía". El aviso no llegaba y el
     * log culpaba a la plantilla, que estaba perfectamente aprobada.
     */
    public function test_ignora_la_waba_de_prueba_y_usa_la_declarada_en_configuracion(): void
    {
        $this->encender();

        // Primero la de prueba, para que se lleve el id más bajo.
        $vieja = WhatsAppAccount::create([
            'waba_id' => '987252317374914',
            'name'    => 'WABA de prueba de Meta',
            'status'  => WhatsAppAccount::STATUS_ACTIVE,
        ]);

        WhatsAppNumber::create([
            'whatsapp_account_id'  => $vieja->id,
            'client_id'            => null,
            'phone_number_id'      => '1201903109667621',
            'display_phone_number' => '+1 555 628-6220',
            'is_active'            => true,
        ]);

        $numero = $this->numeroPropio();
        $this->plantilla($numero);

        // La de prueba quedó con el id más bajo: es la trampa.
        $this->assertLessThan($numero->id, WhatsAppNumber::min('id'));

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.AVISO']]], 200)]);

        $this->ticket()->update(['status' => 'En progreso']);

        Http::assertSent(fn ($r) => str_contains($r->url(), self::PHONE_ID . '/messages'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '1201903109667621'));

        $this->assertSame(1, ConversationMessage::count());
    }

    /**
     * Mandar desde la identidad equivocada es peor que no mandar, y además es
     * invisible: el número de prueba solo entrega a 5 destinatarios dados de
     * alta a mano, así que el aviso se evaporaría sin error.
     */
    public function test_sin_numero_declarado_no_cae_a_cualquier_numero_propio(): void
    {
        $this->encender();
        config([
            'services.whatsapp.phone_number_id'     => null,
            'services.whatsapp.business_account_id' => null,
        ]);

        $numero = $this->numeroPropio();
        $this->plantilla($numero);

        Http::fake();

        $ticket = $this->ticket();
        $ticket->update(['status' => 'En progreso']);

        $this->assertSame('En progreso', $ticket->fresh()->status);
        Http::assertNothingSent();
    }

    /**
     * Meta rechaza los parámetros con saltos de línea o espacios seguidos, y un
     * título pegado desde un correo trae las dos cosas.
     */
    public function test_limpia_los_saltos_de_linea_del_titulo(): void
    {
        $this->encender();
        $numero = $this->numeroPropio();
        $this->plantilla($numero);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.AVISO']]], 200)]);

        $ticket = $this->ticket();
        $ticket->update(['title' => "Banner\n\ncon    saltos", 'status' => 'En progreso']);

        Http::assertSent(function ($r) {
            $parametros = $r['template']['components'][0]['parameters'] ?? [];
            $titulo = $parametros[1]['text'] ?? '';

            return $titulo === 'Banner con saltos';
        });
    }
}
