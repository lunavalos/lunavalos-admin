<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'Ver Finanzas',
            'Crear Finanzas',
            'Editar Finanzas',
            'Eliminar Finanzas'
        ];

        // Ensure Spatie resolves automatically without caching issues
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'Ver Finanzas',
            'Crear Finanzas',
            'Editar Finanzas',
            'Eliminar Finanzas'
        ];

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->delete();
            }
        }
    }
};
