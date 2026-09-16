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
 * Aviso por WhatsApp cuando cambia un ticket.
 *
 * La regla de producto, que es lo que más cuida este archivo: **el aviso es
 * para la persona afectada, nunca para quien hizo el cambio**. Si arrastras una
 * tarjeta tú misma ya sabes que la arrastraste; el WhatsApp sobra y cuesta.
 */
class TicketWhatsAppAlertTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_ID = '1230737580126123';
    private const WABA_ID  = '2436841820155807';

    private function encender(string $plantilla = 'ticket_actualizado'): void
    {
        config([
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
                'text' => 'Hola, hubo un cambio en el ticket {{1}} — {{2}}. {{3}}. Actualizado por {{4}}.',
            ]],
            'body_variables'      => 4,
        ]);
    }

    private function usuario(string $nombre, ?string $whatsapp = null): User
    {
        return User::factory()->create(['name' => $nombre, 'whatsapp' => $whatsapp]);
    }

    private function ticket(?User $responsable = null): Ticket
    {
        return Ticket::create([
            'title'       => 'Banner de septiembre',
            'content'     => 'x',
            'status'      => 'Nuevos',
            'priority'    => 'Media',
            'source_type' => Ticket::SOURCE_SUPPORT,
            'channel'     => Ticket::CHANNEL_WEB,
            'client_id'   => Client::create(['business_name' => 'Grupo Macadam'])->id,
            'creator_id'  => $this->usuario('Creador')->id,
            'assigned_id' => $responsable?->id,
        ]);
    }

    /**
     * Un wamid distinto por envío. Meta nunca repite uno, y
     * `conversation_messages.wa_message_id` es único: un fake que devuelva
     * siempre el mismo id revienta en cuanto un test manda dos mensajes.
     */
    private function fakeEnvioOk(): void
    {
        $n = 0;

        Http::fake(['*' => function () use (&$n) {
            return Http::response(['messages' => [['id' => 'wamid.AVISO' . (++$n)]]], 200);
        }]);
    }

    /* ── El caso central ──────────────────────────────────────────────── */

    public function test_al_asignar_un_ticket_avisa_al_nuevo_responsable(): void
    {
        $this->encender();
        $this->plantilla($this->numeroPropio());
        $this->fakeEnvioOk();

        $luna = $this->usuario('Luna', '528442751165');
        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($luna);
        $this->ticket()->update(['assigned_id' => $juan->id]);

        $mensaje = ConversationMessage::latest('id')->first();
        $this->assertNotNull($mensaje);
        // Le llega a Juan, no a Luna, que fue quien lo movió.
        $this->assertSame('528441112233', $mensaje->conversation->contact_wa_id);
        $this->assertStringContainsString('Te lo asignaron', $mensaje->body);
        $this->assertStringContainsString('Actualizado por Luna', $mensaje->body);
        $this->assertSame(ConversationMessage::AUTHOR_SYSTEM, $mensaje->author_type);
    }

    public function test_un_cambio_de_estado_avisa_al_responsable_no_a_quien_lo_movio(): void
    {
        $this->encender();
        $this->plantilla($this->numeroPropio());
        $this->fakeEnvioOk();

        $luna = $this->usuario('Luna', '528442751165');
        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($luna);
        $this->ticket($juan)->update(['status' => 'En progreso']);

        $mensaje = ConversationMessage::latest('id')->first();
        $this->assertSame('528441112233', $mensaje->conversation->contact_wa_id);
        $this->assertStringContainsString('Pasó de Nuevos a En progreso', $mensaje->body);
    }

    /**
     * El corazón del cambio: si lo hiciste tú, ya lo sabes.
     */
    public function test_no_avisa_a_quien_hizo_el_cambio(): void
    {
        $this->encender();
        Bus::fake();

        $luna = $this->usuario('Luna', '528442751165');

        $this->actingAs($luna);
        $this->ticket($luna)->update(['status' => 'En progreso']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    public function test_si_te_autoasignas_un_ticket_no_te_avisa(): void
    {
        $this->encender();
        Bus::fake();

        $luna = $this->usuario('Luna', '528442751165');

        $this->actingAs($luna);
        $this->ticket()->update(['assigned_id' => $luna->id]);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    /* ── El scheduler ─────────────────────────────────────────────────── */

    /**
     * `tickets:auto-close-in-review` corre `->daily()`, que con `APP_TIMEZONE`
     * en UTC cae a las 18:00 de Saltillo, y cierra en un bucle todos los
     * tickets con 5 días en revisión —un `save()` por ticket—. Sin este gate
     * eso era una ráfaga diaria de WhatsApps a las 6 de la tarde, sin dueño y
     * facturada una por una.
     */
    public function test_un_cambio_sin_sesion_no_avisa_a_nadie(): void
    {
        $this->encender();
        Bus::fake();

        $juan   = $this->usuario('Juan', '528441112233');
        $ticket = $this->ticket($juan);

        // Sin actingAs: es el scheduler, un comando o un job.
        $ticket->update(['status' => 'Completados']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    /* ── Sin destinatario ─────────────────────────────────────────────── */

    public function test_un_ticket_sin_responsable_no_avisa(): void
    {
        $this->encender();
        Bus::fake();

        $this->actingAs($this->usuario('Luna', '528442751165'));
        $this->ticket()->update(['status' => 'En progreso']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    /**
     * Mientras el equipo no tenga sus números cargados esto es lo que va a
     * pasar siempre, así que no puede reventar ni quedarse mudo en el log.
     */
    public function test_un_responsable_sin_whatsapp_no_revienta_el_ticket(): void
    {
        $this->encender();
        $this->plantilla($this->numeroPropio());
        Http::fake();

        $sinNumero = $this->usuario('Juan sin número');

        $this->actingAs($this->usuario('Luna', '528442751165'));
        $ticket = $this->ticket($sinNumero);
        $ticket->update(['status' => 'En progreso']);

        $this->assertSame('En progreso', $ticket->fresh()->status);
        $this->assertSame(0, ConversationMessage::count());
        Http::assertNothingSent();
    }

    /* ── El tope ──────────────────────────────────────────────────────── */

    /**
     * Una acción masiva en el Kanban dispararía una plantilla por ticket al
     * mismo número. Meta lo lee como ráfaga y lo paga el `quality_rating`, que
     * es compartido por todos los clientes de la WABA.
     */
    public function test_corta_la_rafaga_al_mismo_destinatario(): void
    {
        $this->encender();
        $this->plantilla($this->numeroPropio());
        $this->fakeEnvioOk();

        $luna = $this->usuario('Luna', '528442751165');
        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($luna);

        foreach (range(1, 14) as $i) {
            $this->ticket($juan)->update(['status' => "Estado {$i}"]);
        }

        // 10 por hora: el resto se descarta en vez de quemar el número.
        $this->assertSame(10, ConversationMessage::count());
    }

    /* ── Formato del número ───────────────────────────────────────────── */

    public function test_el_numero_del_usuario_se_guarda_como_wa_id(): void
    {
        // Como se escribe un teléfono en Saltillo.
        $this->assertSame('528442751165', $this->usuario('Luna', '844 275 11 65')->whatsapp);
        $this->assertSame('528442751165', $this->usuario('Ana', '+52 844 275 1165')->whatsapp);
        // Lo que no puede ser un teléfono no se guarda a medias.
        $this->assertNull($this->usuario('Bot', '123')->whatsapp);
        $this->assertNull($this->usuario('Vacio', '')->whatsapp);
    }

    /* ── Lo que ya estaba cubierto ────────────────────────────────────── */

    public function test_no_avisa_por_cambios_que_no_son_estado_ni_responsable(): void
    {
        $this->encender();
        Bus::fake();

        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($this->usuario('Luna', '528442751165'));
        $this->ticket($juan)->update(['title' => 'Otro título', 'priority' => 'Alta']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    public function test_apagado_por_defecto_no_encola_nada(): void
    {
        Bus::fake();

        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($this->usuario('Luna', '528442751165'));
        $this->ticket($juan)->update(['status' => 'En progreso']);

        Bus::assertNotDispatched(NotifyTicketUpdate::class);
    }

    public function test_una_plantilla_sin_aprobar_no_rompe_el_cambio_de_ticket(): void
    {
        $this->encender();
        $this->plantilla($this->numeroPropio(), WhatsAppTemplate::STATUS_PENDING);
        Http::fake();

        $juan = $this->usuario('Juan', '528441112233');

        $this->actingAs($this->usuario('Luna', '528442751165'));
        $ticket = $this->ticket($juan);
        $ticket->update(['status' => 'En progreso']);

        $this->assertSame('En progreso', $ticket->fresh()->status);
        $this->assertSame(0, ConversationMessage::count());
        Http::assertNothingSent();
    }

    /**
     * Producción arrastra la WABA de prueba que Meta regala: registrada el
     * 2026-08-19, nunca borrada, y su número también tiene `client_id` null
     * con un id MÁS BAJO que el de la cuenta real.
     *
     * Elegir el número emisor con `first()` cogía ése, y entonces la plantilla
     * —que vive en la WABA de verdad— "no existía". El aviso no llegaba y el
     * log culpaba a la plantilla, que estaba perfectamente aprobada.
     */
    public function test_ignora_la_waba_de_prueba_y_usa_la_declarada_en_configuracion(): void
    {
        $this->encender();

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

        $this->fakeEnvioOk();

        $juan = $this->usuario('Juan', '528441112233');
        $this->actingAs($this->usuario('Luna', '528442751165'));
        $this->ticket($juan)->update(['status' => 'En progreso']);

        Http::assertSent(fn ($r) => str_contains($r->url(), self::PHONE_ID . '/messages'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '1201903109667621'));
        $this->assertSame(1, ConversationMessage::count());
    }

    public function test_sin_numero_declarado_no_cae_a_cualquier_numero_propio(): void
    {
        $this->encender();
        config([
            'services.whatsapp.phone_number_id'     => null,
            'services.whatsapp.business_account_id' => null,
        ]);

        $this->plantilla($this->numeroPropio());
        Http::fake();

        $juan = $this->usuario('Juan', '528441112233');
        $this->actingAs($this->usuario('Luna', '528442751165'));
        $ticket = $this->ticket($juan);
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
        $this->plantilla($this->numeroPropio());
        $this->fakeEnvioOk();

        $juan = $this->usuario('Juan', '528441112233');
        $this->actingAs($this->usuario('Luna', '528442751165'));

        $ticket = $this->ticket($juan);
        $ticket->update(['title' => "Banner\n\ncon    saltos", 'status' => 'En progreso']);

        Http::assertSent(function ($r) {
            $parametros = $r['template']['components'][0]['parameters'] ?? [];

            return ($parametros[1]['text'] ?? '') === 'Banner con saltos';
        });
    }
}
