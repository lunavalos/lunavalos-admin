<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los scopes que salen en la URL del diálogo de Meta tienen que ser
 * exactamente los aprobados en App Review.
 *
 * Socialite fusiona por omisión: `FacebookProvider::$scopes = ['email']`, y
 * `scopes()` hace array_merge. Con eso el diálogo recibía `email` aunque no
 * estuviera en nuestra lista, y Meta respondía "Invalid Scopes: email" y no
 * dejaba conectar ninguna cuenta.
 */
class SocialOauthScopesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        return $user;
    }

    private function queryDelDialogo(string $provider): array
    {
        config([
            'services.facebook.client_id'     => 'test-id',
            'services.facebook.client_secret' => 'test-secret',
            'services.facebook.redirect'      => 'https://admin.test/social/oauth/facebook/callback',
            'services.instagram.redirect'     => 'https://admin.test/social/oauth/instagram/callback',
        ]);

        $client = Client::create(['business_name' => 'Cliente de prueba']);

        $respuesta = $this->actingAs($this->staff())
            ->get("/social/oauth/{$provider}/{$client->id}/redirect");

        $respuesta->assertRedirect();

        parse_str(parse_url($respuesta->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);

        return $query;
    }

    private function scopesDelDialogo(string $provider): array
    {
        return array_filter(explode(',', $this->queryDelDialogo($provider)['scope'] ?? ''));
    }

    public function test_facebook_no_pide_email(): void
    {
        $scopes = $this->scopesDelDialogo('facebook');

        $this->assertNotContains('email', $scopes);
        $this->assertEqualsCanonicalizing([
            'public_profile',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'business_management',
        ], $scopes);
    }

    public function test_instagram_no_pide_email_y_usa_la_familia_de_facebook_login(): void
    {
        $scopes = $this->scopesDelDialogo('instagram');

        $this->assertNotContains('email', $scopes);
        // La familia instagram_business_* es la de Instagram Login, otro flujo.
        $this->assertContains('instagram_basic', $scopes);
        $this->assertContains('instagram_content_publish', $scopes);
        $this->assertNotContains('instagram_business_basic', $scopes);
    }

    public function test_con_configuracion_de_login_for_business_se_manda_config_id_y_no_scope(): void
    {
        config([
            'services.facebook.login_config_id'  => '1234567890',
            'services.instagram.login_config_id' => '1234567890',
        ]);

        $query = $this->queryDelDialogo('facebook');

        // En Login for Business los permisos salen de la configuración del
        // panel; sin config_id el diálogo solo concede el perfil básico y
        // nunca ofrece elegir páginas.
        $this->assertSame('1234567890', $query['config_id'] ?? null);
        // Socialite siempre incluye el parámetro; lo que importa es que vaya
        // vacío, para que no compita con lo que declara la configuración.
        $this->assertSame('', $query['scope'] ?? null);
    }

    public function test_sin_configuracion_se_siguen_mandando_los_scopes(): void
    {
        config([
            'services.facebook.login_config_id'  => null,
            'services.instagram.login_config_id' => null,
        ]);

        $query = $this->queryDelDialogo('facebook');

        $this->assertArrayNotHasKey('config_id', $query);
        $this->assertStringContainsString('pages_manage_posts', $query['scope']);
    }

    public function test_cada_provider_regresa_a_su_propio_callback(): void
    {
        // Instagram usa el driver de Facebook. Si el redirect_uri apunta al
        // callback de facebook, el parámetro de ruta llega como `facebook` y
        // corre handleFacebook(): handleInstagram() queda inalcanzable y no se
        // puede conectar ninguna cuenta de Instagram.
        $this->assertSame(
            'https://admin.test/social/oauth/facebook/callback',
            $this->queryDelDialogo('facebook')['redirect_uri'] ?? null
        );

        $this->assertSame(
            'https://admin.test/social/oauth/instagram/callback',
            $this->queryDelDialogo('instagram')['redirect_uri'] ?? null
        );
    }
}
