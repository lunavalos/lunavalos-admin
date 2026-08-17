<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Los roles de producción (Designer / Web Developer) ya ven el tablero de
 * recurrentes, pero en solo lectura: abrir el ciclo del mes y crear entregables
 * exige `Gestionar Recurrentes`, y sin él los botones del tablero terminaban en
 * un 403. Este permiso es operativo, no comercial: el importe mensual del
 * contrato solo se le manda al rol administrador.
 *
 * Complementa a `2026_08_17_120000_restrict_production_roles_from_commercial_data`.
 */
return new class extends Migration
{
    private const ROLES = ['Designer', 'Web Developer'];

    private const PERMISSION = 'Gestionar Recurrentes';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }

            $role->givePermissionTo($permission);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if (!Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->exists()) {
            return;
        }

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }

            $role->revokePermissionTo(self::PERMISSION);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
