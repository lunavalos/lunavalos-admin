<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos para el módulo de Service Addons.
 * Se asignan automáticamente al rol admin configurado en config/roles.php.
 */
return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'Ver Addons',
            'Crear Addons',
            'Editar Addons',
            'Eliminar Addons',
        ];

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRoleName = config('roles.admin', 'Administrador');
        if ($role = Role::where(['name' => $adminRoleName, 'guard_name' => 'web'])->first()) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        $permissions = [
            'Ver Addons',
            'Crear Addons',
            'Editar Addons',
            'Eliminar Addons',
        ];

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $name) {
            if ($perm = Permission::where(['name' => $name, 'guard_name' => 'web'])->first()) {
                $perm->delete();
            }
        }
    }
};
