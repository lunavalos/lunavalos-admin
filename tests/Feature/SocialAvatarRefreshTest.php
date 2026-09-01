<?php

namespace Tests\Feature;

use App\Console\Commands\RefreshSocialAvatarsCommand;
use App\Models\Client;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Las URLs de foto de perfil de Meta van firmadas y caducan a las pocas horas:
 * la que se guarda al conectar la cuenta acaba devolviendo 403 y en "Cuentas
 * conectadas" quedaba el ícono de imagen rota.
 */
class SocialAvatarRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(string $provider, array $extra = []): SocialAccount
    {
        $client = Client::create(['business_name' => 'Demo']);

        return SocialAccount::create(array_merge([
            'client_id'        => $client->id,
            'provider'         => $provider,
            'provider_user_id' => 'canal_1',
            'name'             => 'Cuenta demo',
            'access_token'     => 'token-demo',
            'avatar_url'       => 'https://scontent.cdninstagram.com/vieja.jpg',
            'status'           => 'active',
            'meta'             => ['page_id' => '123', 'ig_business_id' => '456'],
        ], $extra));
    }

    public function test_instagram_vuelve_a_pedir_la_foto_y_la_guarda(): void
    {
        Http::fake(['*/456*' => Http::response([
            'profile_picture_url' => 'https://scontent.cdninstagram.com/nueva.jpg',
        ], 200)]);

        $cuenta = $this->cuenta('instagram');

        $this->artisan('social:refresh-avatars')
            ->expectsOutputToContain('Avatares actualizados: 1.')
            ->assertSuccessful();

        $this->assertSame('https://scontent.cdninstagram.com/nueva.jpg', $cuenta->fresh()->avatar_url);
    }

    public function test_facebook_pide_la_url_en_json_y_no_la_imagen(): void
    {
        Http::fake(['*/picture*' => Http::response([
            'data' => ['url' => 'https://scontent.xx.fbcdn.net/pagina.jpg'],
        ], 200)]);

        $cuenta = $this->cuenta('facebook');

        $this->artisan('social:refresh-avatars')->assertSuccessful();

        $this->assertSame('https://scontent.xx.fbcdn.net/pagina.jpg', $cuenta->fresh()->avatar_url);
        // Sin redirect=false Graph responde con la imagen, no con su URL.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'redirect=false'));
    }

    public function test_un_token_vencido_no_corta_el_recorrido_ni_borra_la_foto(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Token expirado']], 401)]);

        $cuenta = $this->cuenta('instagram');

        $this->artisan('social:refresh-avatars')
            ->expectsOutputToContain('Avatares actualizados: 0.')
            ->assertSuccessful();

        // Se queda la que había: es mejor una foto vieja que ninguna.
        $this->assertSame('https://scontent.cdninstagram.com/vieja.jpg', $cuenta->fresh()->avatar_url);
    }

    public function test_no_toca_las_cuentas_desconectadas(): void
    {
        Http::fake();

        $this->cuenta('instagram', ['status' => 'revoked']);

        $this->artisan('social:refresh-avatars')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_se_puede_limitar_a_un_cliente(): void
    {
        Http::fake(['*' => Http::response(['profile_picture_url' => 'https://x/nueva.jpg'], 200)]);

        // (provider, provider_user_id) es único: cada cuenta es de un cliente
        // distinto y con su propio id de la red.
        $unaCuenta  = $this->cuenta('instagram', ['provider_user_id' => 'ig_1']);
        $otraCuenta = $this->cuenta('instagram', ['provider_user_id' => 'ig_2']);

        $this->artisan('social:refresh-avatars', ['--client' => $unaCuenta->client_id])
            ->assertSuccessful();

        $this->assertSame('https://x/nueva.jpg', $unaCuenta->fresh()->avatar_url);
        $this->assertSame('https://scontent.cdninstagram.com/vieja.jpg', $otraCuenta->fresh()->avatar_url);
    }
}
