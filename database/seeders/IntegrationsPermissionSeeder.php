<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso de la pantalla de Integraciones.
 *
 * Va aparte del DatabaseSeeder para poder añadirlo en producción sin correr el
 * seeder completo, que además toca roles y datos base.
 *
 * Solo se le da al rol administrador: emitir un token es abrir una puerta al
 * WhatsApp de un cliente, así que se concede a mano a quien deba tenerlo, no
 * por omisión.
 */
class IntegrationsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'Gestionar Integraciones', 'guard_name' => 'web']);

        $adminRoleName = config('roles.admin', 'Administrador');

        if ($role = Role::where('name', $adminRoleName)->first()) {
            $role->givePermissionTo('Gestionar Integraciones');
            $this->command?->info("✓ {$adminRoleName} → «Gestionar Integraciones» asignado.");
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('Permiso de Integraciones creado correctamente.');
    }
}
