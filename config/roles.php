<?php

/*
|--------------------------------------------------------------------------
| Role Definitions
|--------------------------------------------------------------------------
|
| Canonical role names used across the application. All authorization
| checks should reference these values (e.g. `config('roles.admin')`)
| instead of hardcoding role strings. This makes role renames a
| single-file change and avoids drift between code and the database.
|
| Spatie Laravel Permission is the underlying engine. Combined with the
| `Gate::before` hook in `AppServiceProvider`, any user with the
| configured admin role transparently passes every permission check.
|
*/

return [

    /**
     * The privileged administrator role. Bypasses all gates/policies
     * via Gate::before. Treat as the system super-admin.
     */
    'admin' => env('ROLE_ADMIN', 'Administrador'),

    /**
     * The end-customer / portal role.
     */
    'client' => env('ROLE_CLIENT', 'Cliente'),

    /**
     * Cuenta de revisión de plataformas (Meta App Review y equivalentes).
     *
     * Existe porque `EnforceTwoFactorActivation` manda a `profile.edit` a
     * cualquier usuario de staff sin 2FA: un revisor externo entraría y solo
     * vería una pantalla en español pidiéndole activar 2FA, sin llegar nunca
     * a la app. Este rol queda exento de esa obligación.
     *
     * Se asigna únicamente a la cuenta demo que se entrega a la plataforma, y
     * nunca debe usarse para personal interno.
     */
    'reviewer' => env('ROLE_REVIEWER', 'Revisor de Plataforma'),

    /**
     * Internal staff roles eligible to be assigned to tickets and
     * other internal workflows. Order is not significant.
     */
    'staff' => array_values(array_filter(array_unique([
        env('ROLE_ADMIN', 'Administrador'),
        'Web Developer',
        'RRHH',
        'Designer',
    ]))),

    /**
     * Legacy role names that should be migrated/merged into `admin`.
     * Used by the cleanup migration and seeders to keep behavior
     * backwards compatible while removing hardcoded references.
     */
    'legacy_admin_aliases' => [
        'Administrador Master',
    ],

];
