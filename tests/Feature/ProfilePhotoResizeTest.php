<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El avatar se pinta en el layout, así que se descarga en cada vista de la
 * app. Antes se guardaba el archivo tal cual lo subía el usuario y se llegaron
 * a servir PNG de más de 1 MB para pintar un círculo de 20 px.
 *
 * Lo que se fija aquí es que la foto se guarde reducida, pero sin tocar el
 * formato: convertir un PNG con transparencia a JPEG le mete fondo negro.
 */
class ProfilePhotoResizeTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** Sube `$photo` por el formulario de perfil y devuelve la ruta guardada. */
    private function subir(User $user, UploadedFile $photo): string
    {
        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name'  => $user->name,
                'email' => $user->email,
                'photo' => $photo,
            ])
            ->assertSessionHasNoErrors();

        return $user->fresh()->profile_photo_path;
    }

    /** Dimensiones reales del archivo tal como quedó en disco. */
    private function dimensiones(string $path): array
    {
        [$ancho, $alto] = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$ancho, $alto];
    }

    public function test_una_foto_grande_se_guarda_reducida(): void
    {
        Storage::fake('public');
        $user = $this->usuario();

        $path = $this->subir($user, UploadedFile::fake()->image('avatar.jpg', 2400, 1800));

        [$ancho, $alto] = $this->dimensiones($path);

        $this->assertSame(512, max($ancho, $alto), 'El lado mayor debería quedar en 512 px.');
        $this->assertSame(384, min($ancho, $alto), 'La proporción 4:3 original debería conservarse.');
    }

    public function test_una_foto_que_ya_es_chica_se_guarda_sin_tocar(): void
    {
        Storage::fake('public');
        $user = $this->usuario();

        $path = $this->subir($user, UploadedFile::fake()->image('avatar.jpg', 300, 300));

        // Reescribirla solo la re-comprimiría sin ganar tamaño.
        $this->assertSame([300, 300], $this->dimensiones($path));
    }

    public function test_un_png_sigue_siendo_png(): void
    {
        Storage::fake('public');
        $user = $this->usuario();

        $path = $this->subir($user, UploadedFile::fake()->image('avatar.png', 1200, 1200));

        $this->assertStringEndsWith('.png', $path);
        $this->assertSame(
            IMAGETYPE_PNG,
            getimagesizefromstring(Storage::disk('public')->get($path))[2],
            'Pasar un PNG a JPEG le metería fondo negro donde había transparencia.'
        );
        $this->assertSame([512, 512], $this->dimensiones($path));
    }

    public function test_la_foto_guardada_pesa_menos_que_la_subida(): void
    {
        Storage::fake('public');
        $user = $this->usuario();

        $photo = UploadedFile::fake()->image('avatar.jpg', 3000, 3000);
        $pesoOriginal = $photo->getSize();

        $path = $this->subir($user, $photo);

        $this->assertLessThan(
            $pesoOriginal,
            Storage::disk('public')->size($path),
            'La foto guardada debería pesar menos que la que subió el usuario.'
        );
    }
}
