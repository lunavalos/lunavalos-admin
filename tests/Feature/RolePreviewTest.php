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
            ->post(route('role-preview.store'), ['role' => 'Designer'])
            ->assertRedirect(route('dashboard'));

        // Designer no tiene 'Ver Servicios': la ruta debe cerrarse de verdad.
        $this->actingAs($admin)->get(route('services.index'))->assertForbidden();

        // Y sí debe alcanzar lo que Designer sí puede.
        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    }

    public function test_se_puede_salir_del_preview_desde_un_rol_sin_permisos(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['role' => 'Cliente']);

        $this->actingAs($admin)
            ->delete(route('role-preview.destroy'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(RolePreview::SESSION_KEY);

        $this->actingAs($admin)->get(route('services.index'))->assertOk();
    }

    public function test_el_preview_no_toca_los_roles_guardados_en_base_de_datos(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['role' => 'RRHH']);

        $this->assertSame(
            [config('roles.admin')],
            $admin->fresh()->roles()->pluck('name')->all()
        );
    }

    public function test_un_usuario_sin_el_permiso_no_puede_usar_el_switch(): void
    {
        $designer = $this->usuarioConRol('Designer');

        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['role' => config('roles.admin')])
            ->assertForbidden();
    }

    public function test_quien_solo_tiene_el_permiso_de_depuracion_no_puede_ponerse_el_rol_admin(): void
    {
        $designer = $this->usuarioConRol('Designer');
        $designer->givePermissionTo(RolePreview::PERMISSION);

        // Puede previsualizar roles normales…
        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['role' => 'RRHH'])
            ->assertRedirect(route('dashboard'));

        // …pero nunca el de administrador: sería escalación de privilegios.
        $this->actingAs($designer)
            ->post(route('role-preview.store'), ['role' => config('roles.admin')])
            ->assertSessionHasErrors('role');
    }

    public function test_las_props_de_inertia_reflejan_el_rol_previsualizado(): void
    {
        $admin = $this->usuarioConRol(config('roles.admin'));

        $this->actingAs($admin)->post(route('role-preview.store'), ['role' => 'Designer']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_admin', false)
                ->where('role_preview.active', true)
                ->where('role_preview.role', 'Designer')
                ->where('role_preview.real_roles', [config('roles.admin')])
            );
    }
}
