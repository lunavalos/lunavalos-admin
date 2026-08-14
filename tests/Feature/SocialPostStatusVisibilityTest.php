<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La vista de cliente muestra el estado y el error de cada red. Ese detalle
 * solo existe en los targets: el estado del post es un resumen y el composer
 * solo confirma que se encoló.
 *
 * Si alguien restringe las columnas de `targets` en el controlador —como ya se
 * hace con `targets.account`— la interfaz se queda muda y los fallos vuelven a
 * pasar desapercibidos, que es justo lo que pasó al publicar un reel.
 */
class SocialPostStatusVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_recibe_el_estado_y_el_error_de_cada_red(): void
    {
        $client = Client::create(['business_name' => 'Demo']);

        $staff = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $staff->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        $cuenta = fn (string $provider) => SocialAccount::create([
            'client_id'        => $client->id,
            'provider'         => $provider,
            'provider_user_id' => 'id-' . $provider,
            'name'             => ucfirst($provider),
            'access_token'     => 'token',
        ]);

        $post = SocialPost::create([
            'client_id' => $client->id,
            'body'      => 'Contenido',
            'status'    => SocialPost::STATUS_PARTIAL,
        ]);

        SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $cuenta('facebook')->id,
            'provider'          => 'facebook',
            'status'            => SocialPostTarget::STATUS_PUBLISHED,
            'platform_post_id'  => '123_456',
            'platform_url'      => 'https://facebook.com/123_456',
        ]);

        SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $cuenta('instagram')->id,
            'provider'          => 'instagram',
            'status'            => SocialPostTarget::STATUS_FAILED,
            'error_message'     => 'Instagram no pudo procesar el video: formato no soportado',
        ]);

        $this->actingAs($staff)
            ->get("/social/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts.0.targets', 2)
                ->where('posts.0.targets.0.status', SocialPostTarget::STATUS_PUBLISHED)
                ->where('posts.0.targets.0.platform_url', 'https://facebook.com/123_456')
                ->where('posts.0.targets.1.status', SocialPostTarget::STATUS_FAILED)
                ->where(
                    'posts.0.targets.1.error_message',
                    'Instagram no pudo procesar el video: formato no soportado'
                ));
    }
}
