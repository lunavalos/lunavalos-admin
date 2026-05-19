<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge legacy admin role aliases (e.g. "Administrador Master") into the
 * configured canonical admin role, then remove the legacy roles entirely.
 *
 * This migration is idempotent: it only acts on rows that still exist.
 * Role/permission/user assignments are migrated using raw queries against
 * the standard Spatie pivot tables so it works even if the Role model has
 * been customized.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $adminRoleName = config('roles.admin', 'Administrador');
        $legacyAliases = (array) config('roles.legacy_admin_aliases', ['Administrador Master']);
        $guard         = 'web';

        // Ensure canonical admin role exists.
        $adminRoleId = DB::table('roles')
            ->where('name', $adminRoleName)
            ->where('guard_name', $guard)
            ->value('id');

        if (!$adminRoleId) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'name'       => $adminRoleName,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($legacyAliases as $legacyName) {
            $legacyRoleId = DB::table('roles')
                ->where('name', $legacyName)
                ->where('guard_name', $guard)
                ->value('id');

            if (!$legacyRoleId) {
                continue;
            }

            // 1) Reassign users from legacy role to canonical admin role.
            if (Schema::hasTable('model_has_roles')) {
                $assignments = DB::table('model_has_roles')
                    ->where('role_id', $legacyRoleId)
                    ->get();

                foreach ($assignments as $assignment) {
                    $alreadyAssigned = DB::table('model_has_roles')
                        ->where('role_id', $adminRoleId)
                        ->where('model_type', $assignment->model_type)
                        ->where('model_id', $assignment->model_id)
                        ->exists();

                    if (!$alreadyAssigned) {
                        DB::table('model_has_roles')->insert([
                            'role_id'    => $adminRoleId,
                            'model_type' => $assignment->model_type,
                            'model_id'   => $assignment->model_id,
                        ]);
                    }
                }

                DB::table('model_has_roles')->where('role_id', $legacyRoleId)->delete();
            }

            // 2) Copy permissions from legacy role to canonical admin role.
            if (Schema::hasTable('role_has_permissions')) {
                $legacyPermissions = DB::table('role_has_permissions')
                    ->where('role_id', $legacyRoleId)
                    ->pluck('permission_id');

                foreach ($legacyPermissions as $permissionId) {
                    $exists = DB::table('role_has_permissions')
                        ->where('role_id', $adminRoleId)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if (!$exists) {
                        DB::table('role_has_permissions')->insert([
                            'role_id'       => $adminRoleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }

                DB::table('role_has_permissions')->where('role_id', $legacyRoleId)->delete();
            }

            // 3) Delete the legacy role.
            DB::table('roles')->where('id', $legacyRoleId)->delete();
        }

        // Refresh Spatie cache so the changes take effect immediately.
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: re-creating a legacy role with the exact
        // historical user/permission graph is not safely reversible.
    }
};
