<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Los roles de producción (Designer / Web Developer) no deben ver información
 * comercial: el catálogo de servicios expone costos y precios de renovación, y
 * el módulo de clientes expone precios, costos internos y credenciales.
 *
 * Sí conservan el tablero de recurrentes, que es donde reciben su carga de
 * trabajo del mes (créditos y entregables, sin importes).
 */
return new class extends Migration
{
    /** Roles afectados. */
    private const ROLES = ['Designer', 'Web Developer'];

    /** Permisos que se revocan. */
    private const REVOKE = [
        'Ver Clientes',
        'Crear Clientes',
        'Editar Clientes',
        'Eliminar Clientes',
        'Ver Servicios',
        'Crear Servicios',
        'Editar Servicios',
        'Eliminar Servicios',
        'Ver Addons',
        'Crear Addons',
        'Editar Addons',
        'Eliminar Addons',
        'Ver Cotizaciones',
        'Crear Cotizaciones',
        'Editar Cotizaciones',
        'Eliminar Cotizaciones',
        'Convertir Cotizaciones',
        'Ver Contratos',
        'Crear Contratos',
        'Editar Contratos',
        'Ver Finanzas',
        'Crear Finanzas',
        'Editar Finanzas',
        'Eliminar Finanzas',
        'Ver Pagos',
        'Registrar Pagos',
        'Ver Facturas',
        'Emitir Facturas',
    ];

    /** Permisos que se otorgan. */
    private const GRANT = [
        'Ver Recurrentes',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::GRANT as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }

            // revokePermissionTo revienta si el permiso no existe en la tabla,
            // así que filtramos contra los que realmente están registrados.
            $toRevoke = Permission::whereIn('name', self::REVOKE)
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            if ($toRevoke) {
                $role->revokePermissionTo($toRevoke);
            }

            $role->givePermissionTo(self::GRANT);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Restauramos únicamente el estado anterior conocido (ver DatabaseSeeder
        // previo a esta migración): dashboard, servicios, clientes y tickets.
        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }

            $restore = Permission::whereIn('name', ['Ver Servicios', 'Ver Clientes'])
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            if ($restore) {
                $role->givePermissionTo($restore);
            }

            $role->revokePermissionTo(self::GRANT);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
