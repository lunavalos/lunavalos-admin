<?php

return [
    /*
     * Dominio propio para lo que viaja dentro de los correos (link público e
     * imágenes de las firmas), p. ej. https://firmas.lunavalos.com. Debe
     * apuntar a la misma carpeta `public` que el admin.
     *
     * Vacío = se usa el dominio del admin. Los links ya enviados con el
     * dominio del admin siguen funcionando aunque se configure.
     */
    'url' => env('SIGNATURES_URL'),

    // Cuánto pueden guardar navegadores, proxies (Gmail) y nuestro servidor
    // la imagen resuelta antes de volver a preguntar cuál toca.
    'image_cache_seconds' => 300,

    // Store de caché (config/cache.php). En tests va en `array`.
    'cache_store' => env('SIGNATURES_CACHE_STORE', 'signatures'),
];
