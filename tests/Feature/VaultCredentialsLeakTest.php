<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las bóvedas de accesos (`users.vault_credentials`, y en clientes
 * `vault_credentials` / `login_credentials` / `email_accounts`) guardan
 * contraseñas en claro.
 *
 * El riesgo no está en las pantallas que las pintan, sino en que se serializa
 * el modelo completo en props donde nadie las mira: `messages.user` y
 * `assigned` del tablero de tickets, `assets.creator`, `auth.user` en TODAS
 * las páginas… Ahí el dato no se ve, pero va en el HTML y lo lee cualquiera
 * que abra el inspector — incluido un cliente del portal.
 *
 * Por eso van en `$hidden` y se reexponen a mano solo donde se editan. Estas
 * pruebas fijan las dos mitades: que no se filtren, y que las pantallas que
 * sí las necesitan sigan recibiéndolas.
 */
class VaultCredentialsLeakTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_STAFF   = 'hosting-root :: SUPERsecreta123';
    private const VAULT_CLIENTE = 'cpanel :: otraSUPERsecreta456';

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

    private function clienteConBoveda(): Client
    {
        return Client::create([
            'business_name'     => 'Cliente Demo',
            'contact_name'      => 'Contacto',
            'email'             => 'demo@example.com',
            'login_credentials' => self::VAULT_CLIENTE,
            'vault_credentials' => [['platform' => 'GoDaddy', 'password' => 'godaddy-secreta']],
            'email_accounts'    => [['email' => 'hola@demo.com', 'password' => 'correo-secreta']],
        ]);
    }

    /** Un ticket de soporte con un mensaje, para forzar `messages.user`. */
    private function ticketConMensaje(User $creador, User $asignado, Client $client): Ticket
    {
        $ticket = Ticket::create([
            'title'       => 'Se cayó el sitio',
            'content'     => 'No carga',
            'priority'    => 'alta',
            'status'      => 'abierto',
            'source_type' => Ticket::SOURCE_SUPPORT,
            'creator_id'  => $creador->id,
            'assigned_id' => $asignado->id,
            'client_id'   => $client->id,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $asignado->id,
            'message'   => 'Ya lo estoy viendo',
        ]);

        return $ticket;
    }

    public function test_la_boveda_no_viaja_en_auth_user_de_cualquier_pagina(): void
    {
        $admin = $this->usuarioConRol('Administrador', ['vault_credentials' => self::VAULT_STAFF]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(self::VAULT_STAFF, false);
    }

    public function test_el_tablero_de_tickets_no_expone_la_boveda_del_staff(): void
    {
        $admin    = $this->usuarioConRol('Administrador');
        $asignado = $this->usuarioConRol('Designer', ['vault_credentials' => self::VAULT_STAFF]);

        $this->ticketConMensaje($admin, $asignado, $this->clienteConBoveda());

        $this->actingAs($admin)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertDontSee(self::VAULT_STAFF, false);
    }

    public function test_un_cliente_del_portal_no_ve_la_boveda_de_quien_lo_atiende(): void
    {
        $asignado = $this->usuarioConRol('Designer', ['vault_credentials' => self::VAULT_STAFF]);
        $client   = $this->clienteConBoveda();
        $portal   = $this->usuarioConRol('Cliente', ['client_id' => $client->id]);

        $this->ticketConMensaje($portal, $asignado, $client);

        $this->actingAs($portal)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertDontSee(self::VAULT_STAFF, false)
            ->assertDontSee(self::VAULT_CLIENTE, false);
    }

    public function test_el_formulario_de_perfil_sigue_recibiendo_la_boveda_propia(): void
    {
        $admin = $this->usuarioConRol('Administrador', ['vault_credentials' => self::VAULT_STAFF]);

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('vaultCredentials', self::VAULT_STAFF));
    }

    public function test_la_ficha_del_cliente_sigue_recibiendo_su_boveda(): void
    {
        $admin  = $this->usuarioConRol('Administrador');
        $client = $this->clienteConBoveda();

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('client.login_credentials', self::VAULT_CLIENTE)
                ->has('client.vault_credentials', 1)
                ->has('client.email_accounts', 1));
    }
}
