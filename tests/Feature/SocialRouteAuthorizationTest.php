<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El módulo de redes pasó a exigir permiso. Antes bastaba con estar
 * autenticado: el enlace del sidebar sí estaba gateado con `Ver Social`, pero
 * la ruta no validaba nada y cualquier usuario entraba escribiendo la URL.
 *
 * Lo que hay que sostener aquí son las dos puntas: que un usuario sin permiso
 * quede fuera, y que la cuenta de revisión de plataforma —que recorre el flujo
 * completo para Meta— siga entrando.
 */
class SocialRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function cliente(): Client
    {
        return Client::create([
            'business_name' => 'Cliente Demo',
            'contact_name'  => 'Contacto',
            'email'         => 'demo@example.com',
        ]);
    }

    public function test_un_usuario_autenticado_sin_permiso_no_entra(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('social.index'))->assertForbidden();
    }

    public function test_ver_social_no_alcanza_para_publicar(): void
    {
        $cliente = $this->cliente();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('Ver Social', 'web'));

        $this->actingAs($user)->get(route('social.index'))->assertOk();

        // Conectar cuentas y componer posts son otra cosa.
        $this->actingAs($user)
            ->get(route('social.posts.create', $cliente))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('social.oauth.redirect', ['provider' => 'facebook', 'client' => $cliente->id]))
            ->assertForbidden();
    }

    /**
     * El revisor de Meta tiene que poder conectar una página y publicar: es
     * justo el permiso que la plataforma está evaluando.
     */
    public function test_la_cuenta_de_revision_conserva_el_flujo_completo(): void
    {
        $cliente = $this->cliente();

        $role = Role::findOrCreate(config('roles.reviewer', 'Revisor de Plataforma'), 'web');
        $role->syncPermissions([
            Permission::findOrCreate('Ver Social', 'web'),
            Permission::findOrCreate('Gestionar Social', 'web'),
            Permission::findOrCreate('Publicar Social', 'web'),
        ]);

        $revisor = User::factory()->create([
            'client_id'         => $cliente->id,
            'email_verified_at' => now(),
        ]);
        $revisor->assignRole($role);

        $this->actingAs($revisor)->get(route('social.index'))->assertOk();
        $this->actingAs($revisor)->get(route('social.clients.show', $cliente))->assertOk();
        $this->actingAs($revisor)->get(route('social.posts.create', $cliente))->assertOk();
    }

    /**
     * El permiso decide si entras al módulo; el amarre a `users.client_id`
     * decide de qué cliente. Los dos tienen que seguir aplicando.
     */
    public function test_el_permiso_no_anula_el_scoping_por_cliente(): void
    {
        $suyo  = $this->cliente();
        $ajeno = Client::create([
            'business_name' => 'Cliente Real',
            'contact_name'  => 'Otro',
            'email'         => 'real@example.com',
        ]);

        $user = User::factory()->create([
            'client_id'         => $suyo->id,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo(Permission::findOrCreate('Ver Social', 'web'));

        $this->actingAs($user)->get(route('social.clients.show', $suyo))->assertOk();
        $this->actingAs($user)->get(route('social.clients.show', $ajeno))->assertForbidden();
    }
}
