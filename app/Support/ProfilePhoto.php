<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda la foto de perfil redimensionada.
 *
 * El avatar se pinta en el layout, así que viaja en CADA vista de la app. Sin
 * esto se servía el archivo tal cual lo subió el usuario: se han visto PNG de
 * 1.2 MB para pintar un círculo de 20 px.
 *
 * El formato de origen se respeta a propósito: pasar un PNG con transparencia
 * a JPEG le mete un fondo negro. Aquí solo se baja la resolución, que es la
 * parte del peso que no aporta nada.
 *
 * Todo el camino es tolerante a fallos: si GD no está, si el archivo no es una
 * imagen que sepamos leer, o si algo revienta a medias, se guarda el original.
 * Una foto pesada es un problema menor; perder la subida del usuario, no.
 */
class ProfilePhoto
{
    /**
     * Lado máximo en píxeles.
     *
     * El avatar más grande que pinta la app ronda los 128 px, así que 512
     * cubre pantallas retina con margen de sobra.
     */
    private const MAX_SIDE = 512;

    /** Formatos que GD sabe leer y volver a escribir sin sorpresas. */
    private const SUPPORTED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    /** Devuelve la ruta relativa dentro del disco `public`. */
    public static function store(UploadedFile $photo, string $directory = 'profile-photos'): string
    {
        try {
            $reducida = self::resize($photo);

            if ($reducida !== null) {
                [$binario, $extension] = $reducida;
                $path = $directory . '/' . pathinfo($photo->hashName(), PATHINFO_FILENAME) . '.' . $extension;

                Storage::disk('public')->put($path, $binario);

                return $path;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo redimensionar la foto de perfil; se guarda el original.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $photo->store($directory, 'public');
    }

    /**
     * Devuelve `[binario, extensión]`, o `null` si no hay nada que hacer
     * —imagen ya pequeña, formato desconocido, GD ausente—, en cuyo caso el
     * llamador guarda el archivo original.
     */
    private static function resize(UploadedFile $photo): ?array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($photo->getRealPath());

        if ($info === false) {
            return null;
        }

        [$ancho, $alto, $tipo] = $info;

        if (! isset(self::SUPPORTED[$tipo])) {
            return null;
        }

        // Ya cabe: reescribirla solo la re-comprimiría sin ganar nada.
        if ($ancho <= self::MAX_SIDE && $alto <= self::MAX_SIDE) {
            return null;
        }

        $origen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($photo->getRealPath()),
            IMAGETYPE_PNG  => @imagecreatefrompng($photo->getRealPath()),
            IMAGETYPE_GIF  => @imagecreatefromgif($photo->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($photo->getRealPath()),
        };

        if (! $origen) {
            return null;
        }

        try {
            $escala = self::MAX_SIDE / max($ancho, $alto);
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto  = max(1, (int) round($alto * $escala));

            $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

            // Sin esto, lo transparente sale negro en PNG / WEBP / GIF.
            if ($tipo !== IMAGETYPE_JPEG) {
                imagealphablending($lienzo, false);
                imagesavealpha($lienzo, true);
                imagefilledrectangle(
                    $lienzo, 0, 0, $nuevoAncho, $nuevoAlto,
                    imagecolorallocatealpha($lienzo, 0, 0, 0, 127)
                );
            }

            imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

            ob_start();
            match ($tipo) {
                IMAGETYPE_JPEG => imagejpeg($lienzo, null, 85),
                IMAGETYPE_PNG  => imagepng($lienzo, null, 8),
                IMAGETYPE_GIF  => imagegif($lienzo),
                IMAGETYPE_WEBP => imagewebp($lienzo, null, 85),
            };
            $binario = ob_get_clean();

            imagedestroy($lienzo);

            return $binario ? [$binario, self::SUPPORTED[$tipo]] : null;
        } finally {
            imagedestroy($origen);
        }
    }
}
