<?php

namespace App\Support;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

/**
 * Modo "Ver como" (role preview): depurar qué ve cada rol sin mantener
 * usuarios de prueba ni cerrar sesión.
 *
 * La suplantación es de ROL, no de usuario: la identidad, la sesión y la
 * auditoría siguen siendo las del administrador real; lo único que cambia,
 * durante el request y solo en memoria, es:
 *
 *   1. el conjunto de roles/permisos efectivo, y
 *   2. opcionalmente `users.client_id`, el amarre que acota a un cliente.
 *
 * Los dos, porque en esta app la visibilidad no depende solo del rol. La
 * cuenta de revisión de Meta (`platform-reviewer@…`) es el ejemplo exacto:
 * lleva DOS roles a la vez (Revisor de Plataforma + Cliente) y su aislamiento
 * de datos en Social y Conversaciones viene de `users.client_id`, no de
 * ningún rol. Previsualizar un solo rol y sin cliente mostraría algo que esa
 * cuenta NO ve, que es la peor forma de fallar en una herramienta de auditoría.
 *
 * Cómo se propaga: toda la autorización pasa por Spatie (`hasRole`, `can`,
 * `getAllPermissions`) y por el `Gate::before` de AppServiceProvider —que
 * también usa `hasRole`—, así que basta con sustituir las relaciones para que
 * el cambio llegue al backend, a las policies y a las props de Inertia. No se
 * escribe nada en base de datos.
 */
class RolePreview
{
    /** Clave de sesión con el estado del preview. */
    public const SESSION_KEY = 'role_preview';

    /**
     * Permiso que habilita el switch a quien no es administrador. Existe para
     * poder delegar la depuración (p. ej. a un líder técnico) sin regalar el
     * rol de administrador. El admin real pasa por Gate::before y no lo necesita.
     */
    public const PERMISSION = 'Depurar Roles';

    /**
     * La memoria de este servicio vive en el contenedor, no en propiedades
     * estáticas: el contenedor se recicla junto con el request (y entre tests),
     * mientras que una estática sobreviviría al proceso y arrastraría el estado
     * de otro usuario —con ids que se repiten, el caché acertaría por accidente
     * y respondería con los roles de alguien más.
     */
    protected const CACHE_REAL_USER = 'role-preview.real-user';
    protected const CACHE_APPLIED   = 'role-preview.applied';

    /** Limpia la memoria del request. `apply()` la llama al inicio. */
    protected static function flush(): void
    {
        app()->forgetInstance(static::CACHE_REAL_USER);
        app()->forgetInstance(static::CACHE_APPLIED);
    }

    /**
     * El usuario tal y como está en base de datos, sin las relaciones
     * sobreescritas por `apply()`. Es la única fuente de verdad para decidir
     * quién puede usar el switch: de lo contrario un admin en modo "Cliente"
     * perdería la capacidad de salir del modo.
     */
    public static function realUser(User $user): User
    {
        $cached = app()->bound(static::CACHE_REAL_USER) ? app(static::CACHE_REAL_USER) : null;

        if ($cached instanceof User && $cached->getKey() === $user->getKey()) {
            return $cached;
        }

        $real = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->find($user->getKey()) ?? $user;

        app()->instance(static::CACHE_REAL_USER, $real);

        return $real;
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

    /** ¿Este usuario puede usar el switch? */
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
     * Roles que este usuario puede previsualizar. Nunca se puede escalar:
     * quien no es admin real no puede ponerse el rol de administrador.
     */
    public static function previewableRoles(User $user): array
    {
        try {
            $roles = Role::query()->orderBy('name')->pluck('name')->all();
        } catch (\Throwable $e) {
            return [];
        }

        if (static::isRealAdmin($user)) {
            return array_values($roles);
        }

        $admin = (string) config('roles.admin', 'Administrador');

        return array_values(array_filter($roles, fn ($role) => $role !== $admin));
    }

    /**
     * Solo el administrador real puede amarrar el preview a un cliente.
     *
     * Para un admin es una RESTRICCIÓN (pasa de verlo todo a ver un cliente),
     * pero para alguien que solo tiene `Depurar Roles` sería lo contrario:
     * una forma de asomarse a los datos de un cliente que no le tocan.
     */
    public static function canBindClient(?User $user): bool
    {
        return static::isRealAdmin($user);
    }

    /** ¿Hay un preview guardado en la sesión? */
    public static function isActive(): bool
    {
        return ! empty(static::sessionState()['roles'] ?? []);
    }

    /** Estado crudo de la sesión, aún sin validar. */
    protected static function sessionState(): array
    {
        $state = Session::get(static::SESSION_KEY);

        return is_array($state) ? $state : [];
    }

    /**
     * Entrar al modo preview.
     *
     * @param  array<int,string>  $roles  Uno o varios roles simultáneos: hay
     *                                    cuentas reales con más de uno y el
     *                                    preview debe poder reproducirlas.
     */
    public static function start(array $roles, ?int $clientId = null): void
    {
        Session::put(static::SESSION_KEY, [
            'roles'     => array_values(array_unique($roles)),
            'client_id' => $clientId,
        ]);
    }

    /** Salir del modo preview. */
    public static function stop(): void
    {
        Session::forget(static::SESSION_KEY);
        app()->forgetInstance(static::CACHE_APPLIED);
    }

    /**
     * Sobreescribe en memoria los roles/permisos (y el cliente) del usuario
     * autenticado. Devuelve el estado aplicado, o null si no había preview.
     */
    public static function apply(User $user): ?array
    {
        static::flush();

        $state = static::sessionState();
        $roles = array_values(array_filter((array) ($state['roles'] ?? [])));

        if (! $roles) {
            // Devolvemos al usuario sus relaciones reales. Importa cuando la
            // misma instancia de User sobrevive a varios requests (tests,
            // Octane): si no, se quedaría con el rol del preview anterior.
            static::restore($user);

            return null;
        }

        $permitidos = static::previewableRoles($user);

        // Quien perdió el permiso (o el rol admin) mientras tenía un preview
        // abierto vuelve a su rol real de inmediato.
        if (! static::canPreview($user) || array_diff($roles, $permitidos)) {
            static::stop();
            static::restore($user);

            return null;
        }

        $models = Role::query()->with('permissions')->whereIn('name', $roles)->get();

        if ($models->isEmpty()) {
            static::stop();
            static::restore($user);

            return null;
        }

        // Los permisos directos del usuario real se descartan: durante el
        // preview solo cuentan los del rol elegido, que es justo lo que se
        // quiere verificar.
        $user->setRelation('roles', $models);
        $user->setRelation('permissions', collect());

        $clientId = static::applyClient($user, $state['client_id'] ?? null);

        $applied = [
            'roles'     => $models->pluck('name')->values()->all(),
            'client_id' => $clientId,
        ];

        app()->instance(static::CACHE_APPLIED, $applied);

        return $applied;
    }

    /**
     * Amarra el preview a un cliente sobreescribiendo `users.client_id`.
     *
     * `syncOriginalAttribute` es deliberado y no cosmético: sin él, cualquier
     * `save()` sobre el usuario durante el preview —actualizar el perfil, por
     * ejemplo— escribiría ese client_id en la fila del administrador y lo
     * dejaría amarrado a un cliente de verdad. Con el original sincronizado la
     * columna nunca sale sucia y el `save()` no la toca.
     */
    protected static function applyClient(User $user, $clientId): ?int
    {
        if (! $clientId || ! static::canBindClient($user)) {
            return null;
        }

        $cliente = Client::query()->find($clientId);

        if (! $cliente) {
            return null;
        }

        $user->setAttribute('client_id', $cliente->getKey());
        $user->syncOriginalAttribute('client_id');
        $user->setRelation('client', $cliente);

        return (int) $cliente->getKey();
    }

    /** Deshace la sustitución: las relaciones se recargarán desde la base. */
    protected static function restore(User $user): void
    {
        foreach (['roles', 'permissions', 'client'] as $relation) {
            if ($user->relationLoaded($relation)) {
                $user->unsetRelation($relation);
            }
        }
    }

    /** Estado aplicado en este request (null si el usuario ve su rol real). */
    public static function applied(): ?array
    {
        return app()->bound(static::CACHE_APPLIED) ? app(static::CACHE_APPLIED) : null;
    }

    /** Roles aplicados en este request. */
    public static function appliedRoles(): array
    {
        return static::applied()['roles'] ?? [];
    }

    /** Estado que Inertia comparte con el front para pintar el switch. */
    public static function state(?User $user): array
    {
        if (! $user || ! static::canPreview($user)) {
            return [
                'can_preview' => false,
                'active'      => false,
                'roles'       => [],
                'client'      => null,
                'real_roles'  => [],
                'available'   => [],
                'can_bind_client' => false,
            ];
        }

        $applied = static::applied();
        $cliente = null;

        if ($applied && ! empty($applied['client_id'])) {
            $modelo = Client::query()->find($applied['client_id']);
            $cliente = $modelo ? ['id' => $modelo->getKey(), 'name' => $modelo->business_name] : null;
        }

        return [
            'can_preview'     => true,
            'active'          => $applied !== null,
            'roles'           => $applied['roles'] ?? [],
            'client'          => $cliente,
            'real_roles'      => static::realRoleNames($user),
            'available'       => static::previewableRoles($user),
            'can_bind_client' => static::canBindClient($user),
        ];
    }
}
