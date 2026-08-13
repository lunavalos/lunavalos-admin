<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = config('roles.admin', 'Administrador');
        $clientRole = config('roles.client', 'Cliente');

        $roles = array_values(array_unique([
            $adminRole,
            $clientRole,
            'Web Developer',
            'RRHH',
            'Designer',
        ]));

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $permissions = [
            'Ver Dashboard',
            'Ver Clientes',
            'Crear Clientes',
            'Editar Clientes',
            'Eliminar Clientes',
            'Ver Servicios',
            'Crear Servicios',
            'Editar Servicios',
            'Eliminar Servicios',
            'Ver Usuarios',
            'Crear Usuarios',
            'Editar Usuarios',
            'Eliminar Usuarios',
            'Ver Roles',
            'Crear Roles',
            'Editar Roles',
            'Eliminar Roles',
            'Ver Reportes',
            'Crear Reportes',
            'Editar Reportes',
            'Eliminar Reportes',
            'Ver Finanzas',
            'Crear Finanzas',
            'Editar Finanzas',
            'Eliminar Finanzas',
            'Ver RRHH',
            'Gestionar Empleados',
            'Gestionar Salarios',
            'Gestionar Documentos RRHH',
            'Ver Cotizaciones',
            'Crear Cotizaciones',
            'Editar Cotizaciones',
            'Eliminar Cotizaciones',
            'Convertir Cotizaciones',
            'Ver Contratos',
            'Crear Contratos',
            'Editar Contratos',
            'Ver Pagos',
            'Registrar Pagos',
            'Ver Facturas',
            'Emitir Facturas',
            'Ver Addons',
            'Crear Addons',
            'Editar Addons',
            'Eliminar Addons',
            'Ver Recurrentes',
            'Gestionar Recurrentes',
            'Ver Social',
            'Gestionar Social',
            'Publicar Social',
            'Ver Tickets',
            'Crear Tickets',
            'Editar Tickets',
            'Eliminar Tickets',
            'Ver Plantillas de Firma',
            'Crear Plantillas de Firma',
            'Editar Plantillas de Firma',
            'Eliminar Plantillas de Firma',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin: full access (also reinforced via Gate::before in AppServiceProvider).
        $adminRoleModel = Role::firstOrCreate(['name' => $adminRole, 'guard_name' => 'web']);
        $adminRoleModel->syncPermissions($permissions);

        // Default permission matrix for non-admin roles. Edit this map (or the
        // role permissions in the UI) instead of hardcoding role checks in code.
        $ticketPermissions = [
            'Ver Tickets',
            'Crear Tickets',
            'Editar Tickets',
            'Eliminar Tickets',
        ];

        $rolePermissions = [
            $clientRole => array_merge($ticketPermissions, ['Ver Recurrentes']),
            'Web Developer' => array_merge([
                'Ver Dashboard',
                'Ver Servicios',
                'Ver Clientes',
            ], $ticketPermissions),
            'Designer' => array_merge([
                'Ver Dashboard',
                'Ver Servicios',
                'Ver Clientes',
            ], $ticketPermissions),
            'RRHH' => [
                'Ver Dashboard',
                'Ver Roles',
                'Crear Roles',
                'Editar Roles',
                'Eliminar Roles',
                'Ver Usuarios',
                'Crear Usuarios',
                'Editar Usuarios',
                'Eliminar Usuarios',
                'Ver RRHH',
                'Gestionar Empleados',
                'Gestionar Salarios',
                'Gestionar Documentos RRHH',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
