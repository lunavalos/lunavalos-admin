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

    private function scopesDelDialogo(string $provider): array
    {
        config([
            'services.facebook.client_id'     => 'test-id',
            'services.facebook.client_secret' => 'test-secret',
            'services.facebook.redirect'      => 'https://admin.test/social/oauth/facebook/callback',
        ]);

        $client = Client::create(['business_name' => 'Cliente de prueba']);

        $respuesta = $this->actingAs($this->staff())
            ->get("/social/oauth/{$provider}/{$client->id}/redirect");

        $respuesta->assertRedirect();

        parse_str(parse_url($respuesta->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);

        return array_filter(explode(',', $query['scope'] ?? ''));
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
}
