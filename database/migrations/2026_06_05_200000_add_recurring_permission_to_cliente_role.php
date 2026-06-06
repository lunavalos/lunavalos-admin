<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $clientRole = Role::where('name', config('roles.client', 'Cliente'))->first();
        if (!$clientRole) return;

        $permission = Permission::firstOrCreate(['name' => 'Ver Recurrentes', 'guard_name' => 'web']);
        $clientRole->givePermissionTo($permission);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $clientRole = Role::where('name', config('roles.client', 'Cliente'))->first();
        if (!$clientRole) return;

        $clientRole->revokePermissionTo('Ver Recurrentes');
    }
};
