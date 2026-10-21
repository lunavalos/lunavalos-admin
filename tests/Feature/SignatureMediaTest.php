<?php

namespace Tests\Feature;

use App\Models\SignatureMedia;
use App\Models\SignatureTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Biblioteca de imágenes del editor de plantillas.
 *
 *  1. Lo subido al crear (sin id todavía) queda ligado al guardar la plantilla.
 *  2. Reemplazar conserva la URL, que es lo que ya está pegado en las firmas.
 *  3. No se borra una imagen que una plantilla todavía usa.
 */
class SignatureMediaTest extends TestCase
{
    use RefreshDatabase;

    private function editor(bool $conPermiso = true): User
    {
        foreach (['Crear Plantillas de Firma', 'Editar Plantillas de Firma'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();

        if ($conPermiso) {
            $user->givePermissionTo(['Crear Plantillas de Firma', 'Editar Plantillas de Firma']);
        }

        return $user;
    }

    public function test_sin_permiso_no_sube(): void
    {
        $this->actingAs($this->editor(false))
            ->postJson(route('signature-media.store'), ['draft_key' => 'x', 'files' => [UploadedFile::fake()->image('a.png')]])
            ->assertForbidden();
    }

    public function test_lo_subido_al_crear_se_liga_a_la_plantilla(): void
    {
        Storage::fake('public');
        $user = $this->editor();

        $this->actingAs($user)
            ->postJson(route('signature-media.store'), [
                'draft_key' => 'borrador-1',
                'files'     => [UploadedFile::fake()->image('tel.png', 64, 64), UploadedFile::fake()->image('mail.png', 64, 64)],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'media')
            ->assertJsonPath('media.0.width', 64);

        $this->actingAs($user)->post(route('signature-templates.store'), [
            'name'            => 'Absolute',
            'slug'            => 'absolute',
            'html_content'    => '<b>{{name}}</b>',
            'media_draft_key' => 'borrador-1',
        ])->assertRedirect();

        $template = SignatureTemplate::where('slug', 'absolute')->firstOrFail();

        $this->assertSame(2, $template->media()->count());
        $this->assertSame(0, SignatureMedia::whereNotNull('draft_key')->count());
    }

    public function test_rechaza_svg_y_webp(): void
    {
        Storage::fake('public');

        $this->actingAs($this->editor())
            ->postJson(route('signature-media.store'), [
                'draft_key' => 'x',
                'files'     => [UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')],
            ])
            ->assertUnprocessable();
    }

    public function test_reemplazar_conserva_la_url(): void
    {
        Storage::fake('public');
        $user = $this->editor();
        $template = SignatureTemplate::create(['name' => 'T', 'slug' => 't', 'html_content' => 'x']);

        $media = $this->actingAs($user)
            ->postJson(route('signature-media.store'), ['template_id' => $template->id, 'files' => [UploadedFile::fake()->image('logo.png', 100, 40)]])
            ->json('media.0');

        $this->actingAs($user)
            ->postJson(route('signature-media.replace', $media['id']), ['file' => UploadedFile::fake()->image('navidad.png', 120, 40)])
            ->assertOk()
            ->assertJsonPath('media.url', $media['url'])
            ->assertJsonPath('media.width', 120);

        $this->actingAs($user)
            ->postJson(route('signature-media.replace', $media['id']), ['file' => UploadedFile::fake()->image('otro.jpg')])
            ->assertUnprocessable();
    }

    public function test_no_borra_una_imagen_en_uso(): void
    {
        Storage::fake('public');
        $user = $this->editor();
        $template = SignatureTemplate::create(['name' => 'T', 'slug' => 't', 'html_content' => 'x']);

        $media = $this->actingAs($user)
            ->postJson(route('signature-media.store'), ['template_id' => $template->id, 'files' => [UploadedFile::fake()->image('tel.png')]])
            ->json('media.0');

        $template->update(['html_content' => '<img src="' . $media['url'] . '">']);

        $this->actingAs($user)->deleteJson(route('signature-media.destroy', $media['id']))->assertUnprocessable();

        $template->update(['html_content' => 'sin imagen']);

        $this->actingAs($user)->deleteJson(route('signature-media.destroy', $media['id']))->assertOk();
        $this->assertSame(0, SignatureMedia::count());
    }
}
