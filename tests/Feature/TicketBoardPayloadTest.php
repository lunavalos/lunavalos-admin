<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El tablero de tickets llegó a pesar 2.4 MB de HTML.
 *
 * La causa era que la tarjeta pinta el NÚMERO de mensajes, pero el controlador
 * hacía `with('messages.user')`: viajaba el cuerpo de cada mensaje y un `User`
 * completo por mensaje para renderizar un contador. Encima cada `User`
 * serializado disparaba su propia query de roles por los accessors
 * `is_client` / `is_admin`.
 *
 * Lo que se fija aquí es la invariante que importa: ni el peso ni el número de
 * queries del tablero pueden depender de cuántos mensajes tengan los tickets.
 */
class TicketBoardPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(DatabaseSeeder::class);
    }

    private function usuarioConRol(string $rol, array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['email_verified_at' => now()]);
        $user->assignRole($rol);

        return $user;
    }

    private function cliente(): Client
    {
        return Client::create([
            'business_name' => 'Cliente Demo',
            'contact_name'  => 'Contacto',
            'email'         => 'demo@example.com',
        ]);
    }

    /** Siembra `$cuantos` tickets, cada uno con `$mensajes` mensajes. */
    private function sembrar(int $cuantos, int $mensajes, User $creador, User $asignado, Client $client): void
    {
        foreach (range(1, $cuantos) as $i) {
            $ticket = Ticket::create([
                'title'       => "Ticket {$i}",
                'content'     => 'Contenido',
                'priority'    => 'alta',
                'status'      => 'abierto',
                'source_type' => Ticket::SOURCE_SUPPORT,
                'creator_id'  => $creador->id,
                'assigned_id' => $asignado->id,
                'client_id'   => $client->id,
            ]);

            foreach (range(1, $mensajes) as $j) {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => $asignado->id,
                    'message'   => "cuerpo-del-mensaje-{$i}-{$j}",
                ]);
            }
        }
    }

    public function test_la_tarjeta_recibe_el_contador_y_no_los_mensajes(): void
    {
        $admin    = $this->usuarioConRol('Administrador');
        $asignado = $this->usuarioConRol('Designer');

        $this->sembrar(1, 3, $admin, $asignado, $this->cliente());

        $this->actingAs($admin)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tickets', 1)
                ->where('tickets.0.messages_count', 3)
                ->missing('tickets.0.messages'))
            ->assertDontSee('cuerpo-del-mensaje-1-1', false);
    }

    public function test_el_portal_del_cliente_tambien_recibe_solo_el_contador(): void
    {
        $asignado = $this->usuarioConRol('Designer');
        $client   = $this->cliente();
        $portal   = $this->usuarioConRol('Cliente', ['client_id' => $client->id]);

        $this->sembrar(1, 2, $portal, $asignado, $client);

        $this->actingAs($portal)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tickets', 1)
                ->where('tickets.0.messages_count', 2)
                ->missing('tickets.0.messages'))
            ->assertDontSee('cuerpo-del-mensaje-1-1', false);
    }

    public function test_las_queries_no_crecen_con_el_numero_de_usuarios(): void
    {
        $admin  = $this->usuarioConRol('Administrador');
        $client = $this->cliente();

        // Un solo miembro de staff: línea base.
        $this->sembrar(1, 1, $admin, $this->usuarioConRol('Designer'), $client);

        // Petición de calentamiento: la primera visita puebla la caché de
        // permisos de Spatie y gasta queries que no se repiten. Medirla
        // falsearía la comparación a la baja.
        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();

        $pocas = $this->queriesDelTablero($admin);

        // Ahora 15 miembros de staff, cada uno asignado a su propio ticket.
        // Son 15 usuarios DISTINTOS serializados, más los 15 del <select> de
        // asignables: si los accessors `is_client` / `is_admin` se calculan,
        // cada uno pide sus roles por separado.
        foreach (range(1, 15) as $i) {
            $this->sembrar(1, 2, $admin, $this->usuarioConRol('Designer'), $client);
        }

        $muchas = $this->queriesDelTablero($admin);

        $this->assertSame(
            $pocas,
            $muchas,
            "El tablero pasó de {$pocas} a {$muchas} queries al pasar de 1 a 16 usuarios: hay un N+1."
        );
    }

    public function test_el_peso_no_crece_con_el_cuerpo_de_los_mensajes(): void
    {
        $admin    = $this->usuarioConRol('Administrador');
        $asignado = $this->usuarioConRol('Designer');
        $client   = $this->cliente();

        $this->sembrar(10, 1, $admin, $asignado, $client);
        $base = strlen($this->actingAs($admin)->get(route('tickets.index'))->getContent());

        // Mismos 10 tickets, pero cada uno con 20 mensajes largos encima.
        Ticket::all()->each(function ($ticket) use ($asignado) {
            foreach (range(1, 20) as $j) {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => $asignado->id,
                    'message'   => str_repeat('x', 2000),
                ]);
            }
        });

        $conMensajes = strlen($this->actingAs($admin)->get(route('tickets.index'))->getContent());

        // 200 mensajes de 2 KB = 400 KB si viajaran; el contador de dos dígitos
        // más cuesta unos pocos bytes. Un margen de 5 KB distingue las dos cosas
        // sin volverse frágil ante cambios de maquetado.
        $this->assertLessThan(
            5 * 1024,
            $conMensajes - $base,
            "El tablero creció " . ($conMensajes - $base) . " bytes al añadir 200 mensajes: los cuerpos están viajando."
        );
    }

    private function queriesDelTablero(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('tickets.index'))->assertOk();

        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }
}
