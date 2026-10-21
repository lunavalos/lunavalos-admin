<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El permiso de la pantalla de firmas por empleado.
 *
 * Va por migración y no por seeder por la misma razón que
 * `add_manage_ai_agents_permission`: `DatabaseSeeder` usa `syncPermissions()`
 * y le quitaría permisos a los roles que la base ya tiene.
 */
return new class extends Migration
{
    private const PERMISSION = 'Gestionar Firmas de Empleados';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        Role::where('name', config('roles.admin', 'Administrador'))
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo(self::PERMISSION);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
