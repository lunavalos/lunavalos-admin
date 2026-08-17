<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las rutas de `social` pasaron a exigir permiso (antes bastaba con estar
 * autenticado). La cuenta que se entrega a los revisores de plataforma recorre
 * el flujo completo —conectar una página y publicar—, así que hay que
 * declararle explícitamente lo que hasta ahora obtenía por omisión.
 *
 * Sin esto, el rol queda con solo `Ver Social` y el revisor de Meta se topa con
 * un 403 justo en el paso que vino a evaluar.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['Gestionar Social', 'Publicar Social'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', config('roles.reviewer', 'Revisor de Plataforma'))
            ->where('guard_name', 'web')
            ->first();

        if (!$role) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role->givePermissionTo(self::PERMISSIONS);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', config('roles.reviewer', 'Revisor de Plataforma'))
            ->where('guard_name', 'web')
            ->first();

        if (!$role) {
            return;
        }

        $existentes = Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        if ($existentes) {
            $role->revokePermissionTo($existentes);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
