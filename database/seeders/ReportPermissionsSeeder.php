<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReportPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'Ver Reportes',
            'Crear Reportes',
            'Editar Reportes',
            'Eliminar Reportes',
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
            $this->command?->info("✓ {$adminRoleName} → permisos de Reportes asignados.");
        }

        $this->command?->info('Permisos de Reportes creados correctamente.');
    }
}
