<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EmailSignature;
use App\Models\SignatureAsset;
use App\Models\SignatureTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Lo que protege al servidor y al admin de lo que viaja en los correos.
 *
 *  1. Los buscadores no indexan firmas (cabecera, no <meta>: no ven JS).
 *  2. La caché de imágenes no retrasa un cambio hecho en el admin.
 *  3. Los logos se reducen al subirlos; los GIF animados no se tocan.
 *  4. El subdominio de firmas solo sirve /firma/* y los links nuevos lo usan,
 *     sin romper los ya enviados con el dominio del admin.
 */
class SignatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('SIGNATURES_URL');
        unset($_ENV['SIGNATURES_URL'], $_SERVER['SIGNATURES_URL']);

        parent::tearDown();
    }

    private function firma(): EmailSignature
    {
        $template = SignatureTemplate::create([
            'name' => 'Base', 'slug' => 'base-' . uniqid(), 'html_content' => '<img src="{{logo}}"><b>{{name}}</b>', 'is_active' => true,
        ]);
        $client = Client::create(['business_name' => 'Kronos', 'signature_template_id' => $template->id]);

        return EmailSignature::create(['client_id' => $client->id, 'name' => 'Ana']);
    }

    public function test_firma_publica_e_imagenes_piden_no_indexar(): void
    {
        $this->withoutVite();
        $firma = $this->firma();

        $this->get(route('email-signatures.public', $firma->token))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get(route('email-signatures.image', [$firma->token, 'logo']))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_un_logo_nuevo_se_ve_al_momento_aunque_la_imagen_este_en_cache(): void
    {
        Storage::fake('public');
        $firma = $this->firma();
        $url = route('email-signatures.image', [$firma->token, 'logo']);

        Storage::disk('public')->put('viejo.png', 'VIEJO');
        $viejo = SignatureAsset::create(['client_id' => $firma->client_id, 'slot' => 'logo', 'file_path' => 'viejo.png']);
        $this->assertSame('VIEJO', $this->get($url)->streamedContent());

        Storage::disk('public')->put('nuevo.png', 'NUEVO');
        SignatureAsset::create(['client_id' => $firma->client_id, 'slot' => 'logo', 'file_path' => 'nuevo.png']);
        $this->assertSame('NUEVO', $this->get($url)->streamedContent());

        // Revocar también corta lo que estaba en caché.
        $firma->update(['is_active' => false]);
        $this->assertSame('image/png', $this->get($url)->headers->get('Content-Type'));
        $this->assertNotSame('NUEVO', $this->get($url)->getContent());
    }

    public function test_los_logos_se_reducen_al_subirlos(): void
    {
        Storage::fake('public');
        $firma = $this->firma();

        Permission::firstOrCreate(['name' => 'Gestionar Firmas de Empleados', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('Gestionar Firmas de Empleados');

        $this->actingAs($admin)->post(route('signature-assets.store'), [
            'client_id' => $firma->client_id,
            'slot'      => 'logo',
            'file'      => UploadedFile::fake()->image('enorme.png', 3000, 1000),
        ])->assertSessionHasNoErrors();

        [$ancho, $alto] = getimagesize(Storage::disk('public')->path(SignatureAsset::firstOrFail()->file_path));

        $this->assertSame(600, $ancho);
        $this->assertSame(200, $alto);
    }

    public function test_subdominio_de_firmas(): void
    {
        // Las rutas se registran al arrancar: hay que levantar la app con la variable puesta.
        putenv('SIGNATURES_URL=https://firmas.example.test');
        $_ENV['SIGNATURES_URL'] = $_SERVER['SIGNATURES_URL'] = 'https://firmas.example.test';
        $this->refreshApplication();
        $this->artisan('migrate');
        $this->withoutVite();

        $firma = $this->firma();

        // Los links nuevos salen con el subdominio…
        $this->assertStringStartsWith('https://firmas.example.test/firma/', $firma->publicUrl());
        $this->assertStringStartsWith(
            'https://firmas.example.test/storage/',
            \App\Support\SignatureUrls::storage('signature-media/x.png')
        );

        // …y responden ahí.
        $this->get($firma->publicUrl())->assertOk();

        // Los ya enviados con el dominio del admin siguen funcionando.
        $this->get('http://localhost/firma/' . $firma->token)->assertOk();

        // El subdominio no expone el admin.
        $this->get('https://firmas.example.test/login')->assertNotFound();
        $this->get('https://firmas.example.test/dashboard')->assertNotFound();
        $this->get('http://localhost/login')->assertOk();
    }

    public function test_las_imagenes_no_crean_sesion_ni_mandan_cookies(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logo.png', 'LOGO');
        $firma = $this->firma();
        SignatureAsset::create(['client_id' => $firma->client_id, 'slot' => 'logo', 'file_path' => 'logo.png']);

        $respuesta = $this->get(route('email-signatures.image', [$firma->token, 'logo']));

        $respuesta->assertOk();
        $this->assertSame([], $respuesta->headers->getCookies());
        $this->assertStringContainsString('public', $respuesta->headers->get('Cache-Control'));
    }
}
