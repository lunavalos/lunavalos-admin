<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Correr el seeder no debe quitarle permisos a nadie.
 *
 * El 2026-08-21, al ir a crear el permiso de la pantalla de agentes, se
 * comprobó que `php artisan db:seed` en producción le habría quitado los tres
 * permisos de Social a Designer y Web Developer: los tenían concedidos a mano
 * desde la pantalla de roles, el mapa del seeder no los contemplaba, y
 * `syncPermissions()` reemplaza en vez de añadir.
 *
 * Estos tests existen para que eso no pueda volver a pasar en silencio.
 */
class SeederIsNonDestructiveTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): void
    {
        $this->seed(DatabaseSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_no_le_quita_a_un_rol_lo_que_se_le_dio_a_mano(): void
    {
        $this->sembrar();

        // Alguien le da acceso a Social a los diseñadores desde la pantalla de
        // roles. Es el caso real que motivó este test.
        $designer = Role::where('name', 'Designer')->firstOrFail();
        $extra    = ['Ver Social', 'Gestionar Social', 'Publicar Social'];

        foreach ($extra as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $designer->givePermissionTo($extra);
        $antes = $designer->fresh()->permissions->pluck('name')->sort()->values()->all();

        // Se vuelve a correr el seeder, por ejemplo para añadir un permiso nuevo.
        $this->sembrar();

        $despues = $designer->fresh()->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame($antes, $despues, 'El seeder le quitó permisos a un rol existente.');

        foreach ($extra as $nombre) {
            $this->assertTrue($designer->fresh()->hasPermissionTo($nombre));
        }
    }

    public function test_una_instalacion_nueva_si_recibe_la_matriz_por_omision(): void
    {
        $this->sembrar();

        $designer = Role::where('name', 'Designer')->firstOrFail();

        // Lo que la matriz promete para un rol de producción. Si esto deja de
        // aplicarse, una instalación nueva arrancaría con roles vacíos.
        $this->assertTrue($designer->hasPermissionTo('Ver Tickets'));
        $this->assertTrue($designer->hasPermissionTo('Gestionar Recurrentes'));

        // Y lo que la matriz les niega a propósito: información comercial.
        $this->assertFalse($designer->hasPermissionTo('Ver Clientes'));
    }

    public function test_el_administrador_acumula_los_permisos_nuevos(): void
    {
        $this->sembrar();

        $admin = Role::where('name', config('roles.admin', 'Administrador'))->firstOrFail();

        // Un permiso que existía antes de que el seeder corriera de nuevo.
        Permission::firstOrCreate(['name' => 'Permiso Inventado', 'guard_name' => 'web']);
        $admin->givePermissionTo('Permiso Inventado');

        $this->sembrar();

        // Sigue teniéndolo: givePermissionTo añade, no reemplaza.
        $this->assertTrue($admin->fresh()->hasPermissionTo('Permiso Inventado'));
        // Y tiene los de la lista.
        $this->assertTrue($admin->fresh()->hasPermissionTo('Gestionar Agentes IA'));
    }

    public function test_el_rol_de_revisor_no_lo_toca_el_seeder(): void
    {
        $this->sembrar();

        // No está en la matriz, así que debe quedar como lo dejó su seeder
        // propio. Con Meta a punto de revisar, un permiso perdido aquí es un
        // 403 en mitad de la evaluación.
        $revisor = Role::firstOrCreate([
            'name'       => config('roles.reviewer', 'Revisor de Plataforma'),
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate(['name' => 'Ver Conversaciones', 'guard_name' => 'web']);
        $revisor->givePermissionTo('Ver Conversaciones');

        $this->sembrar();

        $this->assertTrue($revisor->fresh()->hasPermissionTo('Ver Conversaciones'));
    }
}
