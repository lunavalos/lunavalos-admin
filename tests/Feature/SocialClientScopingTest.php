<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La cuenta que se entrega a los revisores de plataforma entra al admin real,
 * así que el acotamiento por `users.client_id` es lo único que impide que vea
 * —y publique en— las cuentas de clientes que no le corresponden.
 */
class SocialClientScopingTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(string $nombre): Client
    {
        $client = Client::create(['business_name' => $nombre]);

        SocialAccount::create([
            'client_id'        => $client->id,
            'provider'         => SocialAccount::PROVIDER_FACEBOOK,
            'provider_user_id' => 'page-' . $client->id,
            'name'             => $nombre . ' Page',
            'access_token'     => 'token-' . $client->id,
        ]);

        return $client;
    }

    private function revisor(Client $client): User
    {
        $role = Role::findOrCreate(config('roles.reviewer'), 'web');
        $role->syncPermissions([
            Permission::findOrCreate('Ver Dashboard', 'web'),
            Permission::findOrCreate('Ver Social', 'web'),
        ]);

        $user = User::factory()->create([
            'client_id'         => $client->id,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([
            $role,
            Role::findOrCreate(config('roles.client'), 'web'),
        ]);

        return $user;
    }

    private function ticketDe(Client $client, string $titulo, User $autor): Ticket
    {
        return Ticket::create([
            'title'             => $titulo,
            'content'           => 'contenido',
            'priority'          => 'Media',
            'status'            => 'Nuevos',
            'source_type'       => Ticket::SOURCE_SUPPORT,
            'channel'           => Ticket::CHANNEL_WHATSAPP,
            'creator_id'        => $autor->id,
            'client_id'         => $client->id,
            'visible_to_client' => true,
        ]);
    }

    public function test_el_revisor_solo_ve_su_cliente_en_el_listado(): void
    {
        $suyo  = $this->cliente('Demo Coffee Roasters');
        $ajeno = $this->cliente('Cliente Real');

        $this->actingAs($this->revisor($suyo))
            ->get('/social')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('clients', 1)
                ->where('clients.0.business_name', 'Demo Coffee Roasters')
                ->has('allClients', 1));

        $this->assertDatabaseHas('clients', ['business_name' => $ajeno->business_name]);
    }

    public function test_el_revisor_no_puede_abrir_otro_cliente(): void
    {
        $suyo  = $this->cliente('Demo Coffee Roasters');
        $ajeno = $this->cliente('Cliente Real');

        $this->actingAs($this->revisor($suyo))
            ->get("/social/clients/{$ajeno->id}")
            ->assertForbidden();
    }

    public function test_el_revisor_no_puede_publicar_en_otro_cliente(): void
    {
        $suyo  = $this->cliente('Demo Coffee Roasters');
        $ajeno = $this->cliente('Cliente Real');

        $this->actingAs($this->revisor($suyo))
            ->get("/social/clients/{$ajeno->id}/posts/create")
            ->assertForbidden();
    }

    public function test_el_revisor_no_puede_conectar_cuentas_de_otro_cliente(): void
    {
        $suyo  = $this->cliente('Demo Coffee Roasters');
        $ajeno = $this->cliente('Cliente Real');

        $this->actingAs($this->revisor($suyo))
            ->get("/social/oauth/facebook/{$ajeno->id}/redirect")
            ->assertForbidden();
    }

    public function test_el_staff_sin_client_id_sigue_viendo_todo(): void
    {
        $this->cliente('Demo Coffee Roasters');
        $this->cliente('Cliente Real');

        $staff = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $staff->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        $this->actingAs($staff)
            ->get('/social')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('clients', 2));
    }

    public function test_el_revisor_no_ve_los_tickets_de_otros_clientes(): void
    {
        $suyo  = $this->cliente('Demo Coffee Roasters');
        $ajeno = $this->cliente('Cliente Real');

        $revisor = $this->revisor($suyo);
        $staff   = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);

        $this->ticketDe($suyo,  'WhatsApp demo',              $staff);
        $this->ticketDe($ajeno, 'Conversacion real privada',  $staff);

        $respuesta = $this->actingAs($revisor)->get('/tickets');

        $respuesta->assertOk();
        // El contenido de otros clientes no debe aparecer por ningún lado.
        $respuesta->assertDontSee('Conversacion real privada');
    }
}
