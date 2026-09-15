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

    private function encender(string $plantilla = 'ticket_actualizado'): void
    {
        config([
            'services.whatsapp.ticket_alerts.to'       => self::DESTINO,
            'services.whatsapp.ticket_alerts.template' => $plantilla,
            // El número propio no guarda token en su fila: cae al del system
            // user que vive en configuración.
            'services.whatsapp.token'                  => 'token-del-system-user',
        ]);
    }

    /**
     * El número propio de LunAvalos: `client_id` null, no un Client inventado
     * para representarnos.
     */
    private function numeroPropio(): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::create([
            'waba_id' => '2436841820155807',
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
