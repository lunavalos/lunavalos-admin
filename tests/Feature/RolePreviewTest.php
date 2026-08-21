<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RolePreview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modo "Ver como rol": el administrador recorre el sistema con los permisos de
 * otro rol para depurar qué ve y qué no, sin usuarios de prueba.
 *
 * Lo que se fija aquí es la frontera de verdad: durante el preview las rutas
 * deben responder como para el rol elegido (no basta con esconder el menú), y
 * el switch no puede convertirse en una vía de escalación de privilegios.
 */
class RolePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(DatabaseSeeder::class);
    }

    private function usuarioConRol(string $rol): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($rol);

        return $user;
    }

    public function test_admin_en_preview_pierde_los_permisos_que_el_rol_no_tiene(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        // Como administrador real, el catálogo de servicios está disponible.
        $this->actingAs($admin)->get(route('services.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('role-preview.store'), ['roles' => ['Designer']])
            ->assertRedirect(route('dashboard'));

        // Designer no tiene 'Ver Servicios': la ruta debe cerrarse de verdad.
        $this->actingAs($admin)->get(route('services.index'))->assertForbidden();

        // Y sí debe alcanzar lo que Designer sí puede.
        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    }

    public function test_se_puede_salir_del_preview_desde_un_rol_sin_permisos(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['roles' => ['Cliente']]);

        $this->actingAs($admin)
            ->delete(route('role-preview.destroy'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(RolePreview::SESSION_KEY);

        $this->actingAs($admin)->get(route('services.index'))->assertOk();
    }

    public function test_el_preview_no_toca_los_roles_guardados_en_base_de_datos(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['roles' => ['RRHH']]);

        $this->assertSame(
            [config('roles.admin')],
            $admin->fresh()->roles()->pluck('name')->all()
        );
    }

    public function test_un_usuario_sin_el_permiso_no_puede_usar_el_switch(): void
    {
        $designer = $this->usuarioConRol('Designer');

        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['roles' => [config('roles.admin')]])
            ->assertForbidden();
    }

    public function test_quien_solo_tiene_el_permiso_de_depuracion_no_puede_ponerse_el_rol_admin(): void
    {
        $designer = $this->usuarioConRol('Designer');
        $designer->givePermissionTo(RolePreview::PERMISSION);

        // Puede previsualizar roles normales…
        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['roles' => ['RRHH']])
            ->assertRedirect(route('dashboard'));

        // …pero nunca el de administrador: sería escalación de privilegios.
        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['roles' => [config('roles.admin')]])
            ->assertSessionHasErrors('roles');
    }

    /**
     * La cuenta que se entrega a Meta lleva DOS roles a la vez (Revisor de
     * Plataforma + Cliente). Si el preview solo admitiera uno, un admin
     * "viendo como Revisor" tendría un permiso que esa cuenta no tiene y
     * auditaría algo que no existe.
     */
    public function test_se_pueden_previsualizar_varios_roles_a_la_vez(): void
    {
        // El rol de revisión lo crea PlatformReviewerSeeder, no DatabaseSeeder.
        \Spatie\Permission\Models\Role::findOrCreate(config('roles.reviewer'), 'web');

        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)
            ->post(route('role-preview.store'), [
                'roles' => [config('roles.reviewer'), config('roles.client')],
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('role_preview.roles', [config('roles.client'), config('roles.reviewer')])
                ->where('auth.user.is_client', true)
            );
    }

    /**
     * El aislamiento de la cuenta de revisión no viene de su rol sino de
     * `users.client_id`. Previsualizar sin ese amarre enseñaría datos de otros
     * clientes y daría una falsa alarma (o peor, una falsa tranquilidad).
     */
    public function test_el_preview_puede_acotarse_a_un_cliente(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));
        $cliente = \App\Models\Client::create([
            'business_name' => 'Demo Coffee Roasters',
            'contact_name'  => 'Ana Demo',
            'email'         => 'demo@example.com',
        ]);

        $this->actingAs($admin)->post(route('role-preview.store'), [
            'roles'     => [config('roles.client')],
            'client_id' => $cliente->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('role_preview.client.id', $cliente->id)
                ->where('auth.user.client_id', $cliente->id)
            );
    }

    /**
     * El amarre es solo de lectura en memoria: si una escritura cualquiera lo
     * persistiera, el administrador quedaría atado a un cliente real y perdería
     * el acceso al resto del sistema.
     */
    public function test_el_cliente_del_preview_no_se_guarda_en_el_usuario(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));
        $cliente = \App\Models\Client::create([
            'business_name' => 'Demo Coffee Roasters',
            'contact_name'  => 'Ana Demo',
            'email'         => 'demo@example.com',
        ]);

        $this->actingAs($admin)->post(route('role-preview.store'), [
            'roles'     => [config('roles.client')],
            'client_id' => $cliente->id,
        ]);

        // Una escritura normal sobre el usuario durante el preview.
        $this->actingAs($admin)->patch(route('profile.update'), [
            'name'  => $admin->name,
            'email' => $admin->email,
        ]);

        $this->assertNull($admin->fresh()->client_id);
    }

    /** Acotar a un cliente es privilegio del admin real, no de quien depura. */
    public function test_quien_solo_depura_no_puede_acotar_a_un_cliente(): void
    {
        $designer = $this->usuarioConRol('Designer');
        $designer->givePermissionTo(RolePreview::PERMISSION);

        $cliente = \App\Models\Client::create([
            'business_name' => 'Otro Cliente',
            'contact_name'  => 'Contacto',
            'email'         => 'otro@example.com',
        ]);

        $this->actingAs($designer)
            ->post(route('role-preview.store'), [
                'roles'     => [config('roles.client')],
                'client_id' => $cliente->id,
            ])
            ->assertSessionHasErrors('client_id');
    }

    /** La cuenta de revisión de Meta no debe poder usar el switch. */
    public function test_la_cuenta_de_revision_no_tiene_acceso_al_switch(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate(config('roles.reviewer'), 'web');

        $revisor = User::factory()->create(['email_verified_at' => now()]);
        $revisor->syncRoles([config('roles.reviewer'), config('roles.client')]);

        $this->assertFalse(RolePreview::canPreview($revisor));

        $this->actingAs($revisor)
            ->post(route('role-preview.store'), ['roles' => ['Designer']])
            ->assertForbidden();

        $this->actingAs($revisor)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('role_preview.can_preview', false));
    }

    public function test_las_props_de_inertia_reflejan_el_rol_previsualizado(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['roles' => ['Designer']]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_admin', false)
                ->where('role_preview.active', true)
                ->where('role_preview.roles', ['Designer'])
                ->where('role_preview.real_roles', [config('roles.admin')])
            );
    }
}
