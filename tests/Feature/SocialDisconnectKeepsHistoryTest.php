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
 * Desconectar una red no puede borrar lo ya publicado.
 *
 * `SocialAccount` no usa SoftDeletes, así que `disconnect()` borra la fila de
 * verdad. Con el `cascadeOnDelete` original eso arrastraba todos los
 * `social_post_targets` de esa cuenta —incluidos los de posts publicados, con
 * su `platform_post_id` y su enlace— y reconectar no los recuperaba: el
 * `updateOrCreate` por `provider_user_id` crea una fila nueva porque la vieja
 * ya no existe. Pasó en producción al reconectar Facebook e Instagram.
 */
class SocialDisconnectKeepsHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_desconectar_una_cuenta_conserva_los_targets_publicados(): void
    {
        $client = Client::create(['business_name' => 'Demo']);

        $staff = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $staff->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        $account = SocialAccount::create([
            'client_id'        => $client->id,
            'provider'         => 'instagram',
            'provider_user_id' => '456',
            'name'             => 'Cuenta demo',
            'access_token'     => 'token-demo',
        ]);

        $post = SocialPost::create([
            'client_id'    => $client->id,
            'body'         => 'Ya publicado',
            'status'       => SocialPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $target = SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'provider'          => 'instagram',
            'status'            => SocialPostTarget::STATUS_PUBLISHED,
            'platform_post_id'  => 'ig_1',
            'platform_url'      => 'https://www.instagram.com/p/ABC/',
        ]);

        $this->actingAs($staff)
            ->delete("/social/accounts/{$account->id}")
            ->assertRedirect(route('social.clients.show', $client->id));

        $this->assertDatabaseMissing('social_accounts', ['id' => $account->id]);

        $target->refresh();
        $this->assertNull($target->social_account_id);
        // `provider` está duplicado en el target justo para esto: la etiqueta
        // de red y el enlace se siguen mostrando sin la cuenta.
        $this->assertSame('instagram', $target->provider);
        $this->assertSame('ig_1', $target->platform_post_id);
        $this->assertSame('https://www.instagram.com/p/ABC/', $target->platform_url);
    }
}
