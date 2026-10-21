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
 * Firmas por empleado.
 *
 * Lo que se cuida aquí:
 *
 *  1. **El permiso.** Sin «Gestionar Firmas de Empleados» no se entra.
 *  2. **El link público.** Funciona sin login y deja de funcionar al revocar.
 *  3. **Las imágenes centralizadas.** La firma apunta a una URL fija y esa URL
 *     sirve el logo vigente: subir uno de temporada lo cambia sin tocar la firma.
 */
class EmailSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $conPermiso = true): User
    {
        Permission::firstOrCreate(['name' => 'Gestionar Firmas de Empleados', 'guard_name' => 'web']);

        $user = User::factory()->create();

        if ($conPermiso) {
            $user->givePermissionTo('Gestionar Firmas de Empleados');
        }

        return $user;
    }

    private function firma(array $extra = []): EmailSignature
    {
        $template = SignatureTemplate::create([
            'name'         => 'Base',
            'slug'         => 'base-' . uniqid(),
            'html_content' => '<img src="{{logo}}"><b>{{name}}</b> {{position}}',
            'is_active'    => true,
        ]);

        $client = Client::create(['business_name' => 'Kronos', 'signature_template_id' => $template->id]);

        return EmailSignature::create(array_merge([
            'client_id' => $client->id,
            'name'      => 'Ana López',
            'position'  => 'Ventas',
        ], $extra));
    }

    public function test_sin_permiso_no_entra(): void
    {
        $this->actingAs($this->admin(false))
            ->get(route('email-signatures.index'))
            ->assertForbidden();
    }

    public function test_crea_firma_con_foto_y_token(): void
    {
        Storage::fake('public');
        $client = Client::create(['business_name' => 'Absolute']);

        $this->actingAs($this->admin())
            ->post(route('email-signatures.store'), [
                'client_id' => $client->id,
                'name'      => 'Ana López',
                'email'     => 'ana@example.com',
                'photo'     => UploadedFile::fake()->image('ana.jpg', 200, 200),
                'social_links' => ['linkedin' => 'https://linkedin.com/in/ana', 'myspace' => 'https://x.test'],
            ])
            ->assertRedirect();

        $firma = EmailSignature::firstOrFail();

        $this->assertSame(40, strlen($firma->token));
        $this->assertSame(['linkedin' => 'https://linkedin.com/in/ana'], $firma->social_links);
        Storage::disk('public')->assertExists($firma->photo_path);
    }

    public function test_link_publico_sin_login_y_escapa_datos(): void
    {
        $this->withoutVite();
        $firma = $this->firma(['name' => '<script>x</script>']);

        $this->get(route('email-signatures.public', $firma->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('EmailSignatures/PublicShow')
                ->where('html', fn ($html) => str_contains($html, '&lt;script&gt;')
                    && str_contains($html, route('email-signatures.image', [$firma->token, 'logo']))));

        $this->assertNotNull($firma->fresh()->last_viewed_at);
    }

    public function test_firma_revocada_no_abre_y_sus_imagenes_son_transparentes(): void
    {
        $firma = $this->firma(['is_active' => false]);

        $this->get(route('email-signatures.public', $firma->token))->assertNotFound();

        $this->get(route('email-signatures.image', [$firma->token, 'logo']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_logo_de_temporada_le_gana_al_de_siempre_mientras_esta_vigente(): void
    {
        Storage::fake('public');
        $firma = $this->firma();

        Storage::disk('public')->put('siempre.png', 'SIEMPRE');
        Storage::disk('public')->put('navidad.png', 'NAVIDAD');

        SignatureAsset::create(['client_id' => $firma->client_id, 'slot' => 'logo', 'file_path' => 'siempre.png']);
        SignatureAsset::create([
            'client_id' => $firma->client_id,
            'slot'      => 'logo',
            'file_path' => 'navidad.png',
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
        ]);
        // Ya vencido: no debe ganar aunque sea más reciente.
        SignatureAsset::create([
            'client_id' => $firma->client_id,
            'slot'      => 'logo',
            'file_path' => 'siempre.png',
            'starts_at' => now()->subMonth(),
            'ends_at'   => now()->subWeek(),
        ]);

        $url = route('email-signatures.image', [$firma->token, 'logo']);

        $this->assertSame('NAVIDAD', $this->get($url)->streamedContent());

        $this->travel(3)->days();

        $this->assertSame('SIEMPRE', $this->get($url)->streamedContent());
    }

    public function test_sube_logo_desde_admin(): void
    {
        Storage::fake('public');
        $firma = $this->firma();

        $this->actingAs($this->admin())
            ->post(route('signature-assets.store'), [
                'client_id' => $firma->client_id,
                'slot'      => 'logo',
                'file'      => UploadedFile::fake()->image('logo.png', 300, 100),
            ])
            ->assertRedirect();

        $asset = SignatureAsset::firstOrFail();
        Storage::disk('public')->assertExists($asset->file_path);
        $this->assertSame($asset->id, SignatureAsset::current($firma->client_id, 'logo')->id);
    }
}
