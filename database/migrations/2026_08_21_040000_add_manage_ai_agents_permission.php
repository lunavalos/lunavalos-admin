<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El permiso de la pantalla de agentes de IA.
 *
 * Va por migración y no por `db:seed`: `DatabaseSeeder` usa `syncPermissions()`
 * sobre los roles no administradores, que **reemplaza** en vez de añadir. Su
 * mapa se quedó atrás respecto a la base —Web Developer y Designer tienen hoy
 * los tres permisos de Social y el mapa no los contempla—, así que correrlo
 * para conseguir este permiso se los quitaría de paso.
 *
 * Solo se le concede al rol de administrador. El resto se asigna a mano desde
 * la pantalla de roles: quién puede cambiar el prompt de un agente —lo que el
 * negocio le dice a sus clientes en automático— es una decisión de cada
 * organización, no algo que deba decidir una migración.
 */
return new class extends Migration
{
    private const PERMISSION = 'Gestionar Agentes IA';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        $admin = Role::where('name', config('roles.admin', 'Administrador'))
            ->where('guard_name', 'web')
            ->first();

        // `givePermissionTo` añade; no toca lo que el rol ya tenga.
        $admin?->givePermissionTo(self::PERMISSION);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Se borra el permiso entero: al desaparecer la pantalla, dejarlo
        // suelto solo confundiría en la lista de roles. Spatie limpia las
        // asignaciones al eliminarlo.
        Permission::where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
