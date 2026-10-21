<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Dominio y caché de las firmas de correo.
 */
class SignatureUrls
{
    /** Host del subdominio de firmas, o null si no está configurado. */
    public static function host(): ?string
    {
        $url = config('signatures.url');

        return $url ? parse_url($url, PHP_URL_HOST) : null;
    }

    /**
     * URL absoluta de una ruta de firmas (`email-signatures.*`).
     *
     * Con subdominio se arma con su esquema y host tal cual están
     * configurados, no con los de la petición actual: en el admin la petición
     * viene de otro host y, en local, sin https.
     */
    public static function route(string $name, array $parameters = []): string
    {
        $root = config('signatures.url');

        return $root
            ? rtrim($root, '/') . route($name, $parameters, false)
            : route($name, $parameters);
    }

    /** URL de un archivo del disco `public`, con el dominio de firmas si hay. */
    public static function storage(string $path): string
    {
        $root = config('signatures.url');

        return $root
            ? rtrim($root, '/') . '/storage/' . ltrim($path, '/')
            : asset('storage/' . ltrim($path, '/'));
    }

    /**
     * Llave de caché que cambia de "generación" cada vez que el admin toca
     * algo de firmas. Así no hay que borrar llaves una por una: cualquier
     * cambio invalida todo lo resuelto, y esos cambios son pocos.
     */
    public static function cacheKey(string $key): string
    {
        return 'firmas:' . static::cache()->rememberForever('firmas:gen', fn () => Str::random(8)) . ':' . $key;
    }

    public static function flushCache(): void
    {
        static::cache()->forever('firmas:gen', Str::random(8));
    }

    public static function cache(): Repository
    {
        return Cache::store(config('signatures.cache_store'));
    }
}
