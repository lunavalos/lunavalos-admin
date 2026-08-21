<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

/**
 * Modo "Ver como rol" (role preview).
 *
 * Permite a un administrador recorrer el sistema con los roles y permisos de
 * otro rol sin cerrar sesión ni mantener usuarios de prueba. La suplantación es
 * de ROL, no de usuario: la identidad, la sesión y la auditoría siguen siendo
 * las del administrador real; lo único que cambia es el conjunto de
 * roles/permisos efectivo durante el request.
 *
 * Cómo funciona: `apply()` sustituye en memoria las relaciones `roles` y
 * `permissions` del usuario autenticado. Como TODA la autorización de la app
 * pasa por Spatie (`hasRole`, `can`, `getAllPermissions`) y por el `Gate::before`
 * de AppServiceProvider —que también usa `hasRole`—, el cambio se propaga solo
 * al backend, a las policies y a las props que Inertia comparte con el front.
 * No se escribe nada en base de datos.
 */
class RolePreview
{
    /** Clave de sesión donde vive el rol que se está previsualizando. */
    public const SESSION_KEY = 'role_preview.role';

    /**
     * Permiso que habilita el switch a roles NO administradores. Existe para
     * poder delegar la depuración (p. ej. a un líder técnico) sin regalar el
     * rol de administrador. El rol admin real siempre puede, vía Gate::before.
     */
    public const PERMISSION = 'Depurar Roles';

    /** Copia del usuario con sus roles REALES, cacheada por request. */
    protected static ?User $realUser = null;

    /** Rol efectivamente aplicado en este request (null si no hay preview). */
    protected static ?string $applied = null;

    /**
     * Limpia la memoria del request. `apply()` la llama al inicio: el
     * middleware corre una vez por request, y sin este reset las estáticas
     * sobrevivirían entre requests dentro de un mismo proceso (tests, Octane)
     * y arrastrarían el estado de otro usuario.
     */
    protected static function flush(): void
    {
        static::$realUser = null;
        static::$applied = null;
    }

    /**
     * El usuario tal y como está en base de datos, sin las relaciones
     * sobreescritas por `apply()`. Es la única fuente de verdad para decidir
     * quién puede usar el switch: de lo contrario un admin en modo "Cliente"
     * perdería la capacidad de salir del modo.
     */
    public static function realUser(User $user): User
    {
        if (static::$realUser && static::$realUser->getKey() === $user->getKey()) {
            return static::$realUser;
        }

        return static::$realUser = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->find($user->getKey()) ?? $user;
    }

    /** Nombres de los roles reales del usuario. */
    public static function realRoleNames(User $user): array
    {
        return static::realUser($user)->roles->pluck('name')->all();
    }

    /** ¿El usuario es administrador de verdad (ignorando el preview)? */
    public static function isRealAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array(
            (string) config('roles.admin', 'Administrador'),
            static::realRoleNames($user),
            true
        );
    }

    /** ¿Este usuario puede usar el switch de roles? */
    public static function canPreview(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            if (static::isRealAdmin($user)) {
                return true;
            }

            return static::realUser($user)
                ->getAllPermissions()
                ->contains('name', static::PERMISSION);
        } catch (\Throwable $e) {
            // Spatie sin migrar todavía: no ofrecemos el switch.
            return false;
        }
    }

    /**
     * Roles que este usuario puede previsualizar. Nunca se puede escalar: quien
     * no es admin real no puede ponerse el rol de administrador.
     */
    public static function previewableRoles(User $user): array
    {
        try {
            $roles = Role::query()->orderBy('name')->pluck('name')->all();
        } catch (\Throwable $e) {
            return [];
        }

        $admin = (string) config('roles.admin', 'Administrador');

        // El rol propio real no se "previsualiza": para eso está salir del modo.
        $real = static::realRoleNames($user);

        return array_values(array_filter($roles, function ($role) use ($user, $admin, $real) {
            if (! static::isRealAdmin($user) && $role === $admin) {
                return false;
            }

            return ! in_array($role, $real, true);
        }));
    }

    /** ¿Hay un preview activo en la sesión? */
    public static function isActive(): bool
    {
        return (bool) Session::get(static::SESSION_KEY);
    }

    /** Rol previsualizado según la sesión (aún sin validar). */
    public static function sessionRole(): ?string
    {
        $role = Session::get(static::SESSION_KEY);

        return $role ? (string) $role : null;
    }

    /** Entrar al modo preview con el rol indicado. */
    public static function start(string $role): void
    {
        Session::put(static::SESSION_KEY, $role);
    }

    /** Salir del modo preview. */
    public static function stop(): void
    {
        Session::forget(static::SESSION_KEY);
        static::$applied = null;
    }

    /**
     * Sobreescribe en memoria los roles/permisos del usuario autenticado.
     * Devuelve el rol aplicado, o null si no había preview válido.
     */
    public static function apply(User $user): ?string
    {
        static::flush();

        $role = static::sessionRole();

        if (! $role) {
            // Devolvemos al usuario sus relaciones reales. Importa cuando la
            // misma instancia de User sobrevive a varios requests (tests,
            // Octane): si no, se quedaría con el rol del preview anterior.
            static::restore($user);

            return null;
        }

        // Quien perdió el permiso (o el rol admin) mientras tenía un preview
        // abierto vuelve a su rol real de inmediato.
        if (! static::canPreview($user) || ! in_array($role, static::previewableRoles($user), true)) {
            static::stop();
            static::restore($user);

            return null;
        }

        $model = Role::query()->with('permissions')->where('name', $role)->first();

        if (! $model) {
            static::stop();
            static::restore($user);

            return null;
        }

        // Los permisos directos del usuario real se descartan: durante el
        // preview solo cuentan los del rol elegido, que es justo lo que se
        // quiere verificar.
        $user->setRelation('roles', collect([$model]));
        $user->setRelation('permissions', collect());

        return static::$applied = $role;
    }

    /** Deshace la sustitución de relaciones: se recargarán desde la base. */
    protected static function restore(User $user): void
    {
        foreach (['roles', 'permissions'] as $relation) {
            if ($user->relationLoaded($relation)) {
                $user->unsetRelation($relation);
            }
        }
    }

    /** Rol aplicado en este request (null si el usuario ve su rol real). */
    public static function applied(): ?string
    {
        return static::$applied;
    }

    /** Estado que Inertia comparte con el front para pintar el switch. */
    public static function state(?User $user): array
    {
        if (! $user || ! static::canPreview($user)) {
            return [
                'can_preview'  => false,
                'active'       => false,
                'role'         => null,
                'real_roles'   => [],
                'available'    => [],
            ];
        }

        return [
            'can_preview' => true,
            'active'      => static::applied() !== null,
            'role'        => static::applied(),
            'real_roles'  => static::realRoleNames($user),
            'available'   => static::previewableRoles($user),
        ];
    }
}
