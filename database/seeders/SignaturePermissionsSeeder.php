<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SignaturePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'Ver Plantillas de Firma',
            'Crear Plantillas de Firma',
            'Editar Plantillas de Firma',
            'Eliminar Plantillas de Firma',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign all 4 to the configured admin role
        $adminRoleName = config('roles.admin', 'Administrador');
        $role = Role::where('name', $adminRoleName)->first();
        if ($role) {
            $role->givePermissionTo($permissions);
            $this->command?->info("✓ {$adminRoleName} → permisos de Plantillas de Firma asignados.");
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('Permisos de Plantillas de Firma creados correctamente.');
    }
}
