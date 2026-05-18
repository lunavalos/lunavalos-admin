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

        $roles = [
            'Administrador',
            'Administrador Master',
            'Cliente',
            'Web Developer',
            'RRHH',
            'Designer',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $permissions = [
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRoles = ['Administrador', 'Administrador Master'];
        foreach ($adminRoles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($permissions);
        }
    }
}
