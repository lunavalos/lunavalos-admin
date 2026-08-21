<?php

namespace Database\Seeders;

use App\Support\RolePreview;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crea el permiso que habilita el modo "Ver como rol" en bases de datos que ya
 * existían antes de la feature. Idempotente: se puede correr las veces que sea.
 */
class RolePreviewPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(['name' => RolePreview::PERMISSION, 'guard_name' => 'web']);

        $adminRoleName = config('roles.admin', 'Administrador');

        if ($role = Role::where('name', $adminRoleName)->first()) {
            $role->givePermissionTo(RolePreview::PERMISSION);
            $this->command?->info("✓ {$adminRoleName} → permiso «" . RolePreview::PERMISSION . "» asignado.");
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
