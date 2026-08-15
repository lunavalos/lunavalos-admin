<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Una sola cuenta de Facebook administra páginas de VARIOS clientes de la
 * agencia. El token alcanza todas, así que hay que elegir cuáles se conectan a
 * qué cliente.
 *
 * Y como el índice único es (provider, provider_user_id), una página vive en un
 * solo cliente: conectarla en otro se la quita al que la tenía. Eso nunca debe
 * pasar sin que alguien lo marque a propósito.
 */
class SocialConnectSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        return $user;
    }

    private function candidato(string $id, string $nombre): array
    {
        return [
            'provider'         => 'facebook',
            'provider_user_id' => $id,
            'name'             => $nombre,
            'handle'           => strtolower($nombre),
            'avatar_url'       => null,
            'access_token'     => 'page-token-' . $id,
            'meta'             => ['page_id' => $id, 'page_name' => $nombre],
        ];
    }

    private function conPendientes(Client $client, array $candidatos): array
    {
        return [
            'social_oauth.pendientes' => Crypt::encrypt([
                'client_id'  => $client->id,
                'provider'   => 'facebook',
                'candidatos' => $candidatos,
            ]),
        ];
    }

    public function test_la_pantalla_lista_los_candidatos_sin_exponer_tokens(): void
    {
        $client = Client::create(['business_name' => 'Cliente A']);

        $respuesta = $this->actingAs($this->staff())
            ->withSession($this->conPendientes($client, [
                $this->candidato('111', 'LunAvalos'),
                $this->candidato('222', 'Banda Altavox'),
            ]))
            ->get("/social/clients/{$client->id}/conexiones/elegir");

        $respuesta->assertOk()->assertInertia(fn ($page) => $page
            ->component('Social/ConnectSelect')
            ->has('accounts', 2)
            ->where('accounts.0.name', 'LunAvalos'));

        // Los page tokens no deben viajar al navegador.
        $respuesta->assertDontSee('page-token-111');
    }

    public function test_solo_guarda_las_cuentas_marcadas(): void
    {
        $client = Client::create(['business_name' => 'Cliente A']);

        $this->actingAs($this->staff())
            ->withSession($this->conPendientes($client, [
                $this->candidato('111', 'LunAvalos'),
                $this->candidato('222', 'Banda Altavox'),
                $this->candidato('333', 'Absolute Group'),
            ]))
            ->post("/social/clients/{$client->id}/conexiones/elegir", [
                'provider_user_ids' => ['222'],
            ])
            ->assertRedirect(route('social.clients.show', $client->id));

        $this->assertSame(1, SocialAccount::count());
        $this->assertDatabaseHas('social_accounts', [
            'client_id'        => $client->id,
            'provider_user_id' => '222',
            'name'             => 'Banda Altavox',
        ]);
    }

    public function test_no_toca_las_paginas_de_otro_cliente_que_no_se_marcaron(): void
    {
        $clienteA = Client::create(['business_name' => 'Cliente A']);
        $clienteB = Client::create(['business_name' => 'Cliente B']);

        // Cliente A ya tiene LunAvalos conectada.
        SocialAccount::create([
            'client_id'        => $clienteA->id,
            'provider'         => 'facebook',
            'provider_user_id' => '111',
            'name'             => 'LunAvalos',
            'access_token'     => 'token-viejo',
        ]);

        // Al conectar desde el cliente B el token alcanza las dos páginas,
        // pero solo se marca la que sí es de B.
        $this->actingAs($this->staff())
            ->withSession($this->conPendientes($clienteB, [
                $this->candidato('111', 'LunAvalos'),
                $this->candidato('222', 'Banda Altavox'),
            ]))
            ->post("/social/clients/{$clienteB->id}/conexiones/elegir", [
                'provider_user_ids' => ['222'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('social_accounts', [
            'provider_user_id' => '111',
            'client_id'        => $clienteA->id,
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider_user_id' => '222',
            'client_id'        => $clienteB->id,
        ]);
    }

    public function test_marcar_una_pagina_ajena_la_mueve_a_este_cliente(): void
    {
        $clienteA = Client::create(['business_name' => 'Cliente A']);
        $clienteB = Client::create(['business_name' => 'Cliente B']);

        SocialAccount::create([
            'client_id'        => $clienteA->id,
            'provider'         => 'facebook',
            'provider_user_id' => '111',
            'name'             => 'LunAvalos',
            'access_token'     => 'token-viejo',
        ]);

        $this->actingAs($this->staff())
            ->withSession($this->conPendientes($clienteB, [$this->candidato('111', 'LunAvalos')]))
            ->post("/social/clients/{$clienteB->id}/conexiones/elegir", [
                'provider_user_ids' => ['111'],
            ])
            ->assertRedirect();

        // Movimiento explícito: se marcó a propósito, con el aviso en pantalla.
        $this->assertSame(1, SocialAccount::where('provider_user_id', '111')->count());
        $this->assertDatabaseHas('social_accounts', [
            'provider_user_id' => '111',
            'client_id'        => $clienteB->id,
        ]);
    }

    public function test_la_pantalla_avisa_de_que_pagina_es_de_otro_cliente(): void
    {
        $clienteA = Client::create(['business_name' => 'Cliente A']);
        $clienteB = Client::create(['business_name' => 'Cliente B']);

        SocialAccount::create([
            'client_id'        => $clienteA->id,
            'provider'         => 'facebook',
            'provider_user_id' => '111',
            'name'             => 'LunAvalos',
            'access_token'     => 'token-viejo',
        ]);

        $this->actingAs($this->staff())
            ->withSession($this->conPendientes($clienteB, [$this->candidato('111', 'LunAvalos')]))
            ->get("/social/clients/{$clienteB->id}/conexiones/elegir")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.owner.client_id', $clienteA->id)
                ->where('accounts.0.owner.client_name', 'Cliente A'));
    }

    public function test_no_se_puede_usar_la_seleccion_de_otro_cliente(): void
    {
        $clienteA = Client::create(['business_name' => 'Cliente A']);
        $clienteB = Client::create(['business_name' => 'Cliente B']);

        $this->actingAs($this->staff())
            ->withSession($this->conPendientes($clienteA, [$this->candidato('111', 'LunAvalos')]))
            ->post("/social/clients/{$clienteB->id}/conexiones/elegir", [
                'provider_user_ids' => ['111'],
            ])
            ->assertForbidden();

        $this->assertSame(0, SocialAccount::count());
    }

    public function test_sin_seleccion_pendiente_responde_expirado(): void
    {
        $client = Client::create(['business_name' => 'Cliente A']);

        $this->actingAs($this->staff())
            ->get("/social/clients/{$client->id}/conexiones/elegir")
            ->assertStatus(410);
    }
}
