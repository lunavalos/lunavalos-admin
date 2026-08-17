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
 * Un post publicado no se puede editar: `updatePost` responde 422. La pantalla
 * muestra un resumen en vez del formulario, y para eso necesita los datos de
 * cada red —incluido el nombre de la cuenta y el enlace a la publicación—.
 */
class SocialPublishedPostViewTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        return $user;
    }

    private function escenario(): array
    {
        $client = Client::create(['business_name' => 'Demo']);

        $account = SocialAccount::create([
            'client_id'        => $client->id,
            'provider'         => 'facebook',
            'provider_user_id' => '111',
            'name'             => 'LunAvalos',
            'access_token'     => 'token',
        ]);

        $post = SocialPost::create([
            'client_id'    => $client->id,
            'body'         => 'Contenido publicado',
            'status'       => SocialPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'provider'          => 'facebook',
            'status'            => SocialPostTarget::STATUS_PUBLISHED,
            'platform_post_id'  => '111_222',
            'platform_url'      => 'https://facebook.com/111_222',
            'published_at'      => now(),
        ]);

        return [$client, $post, $account];
    }

    public function test_la_pantalla_recibe_el_detalle_de_cada_red(): void
    {
        [$client, $post] = $this->escenario();

        $this->actingAs($this->staff())
            ->get("/social/clients/{$client->id}/posts/{$post->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Social/PostComposer')
                ->where('post.status', SocialPost::STATUS_PUBLISHED)
                ->where('post.targets.0.platform_url', 'https://facebook.com/111_222')
                // El nombre de la cuenta se muestra en el resumen.
                ->where('post.targets.0.account.name', 'LunAvalos')
                ->has('post.published_at'));
    }

    public function test_duplicar_crea_un_borrador_con_el_mismo_contenido_y_redes(): void
    {
        [$client, $post, $account] = $this->escenario();

        $respuesta = $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts/{$post->id}/duplicate");

        $copia = SocialPost::where('id', '!=', $post->id)->firstOrFail();

        $respuesta->assertRedirect(route('social.posts.edit', [$client->id, $copia->id]));

        $this->assertSame(SocialPost::STATUS_DRAFT, $copia->status);
        $this->assertSame('Contenido publicado', $copia->body);
        $this->assertNull($copia->published_at);

        // Conserva las redes destino, pero en estado inicial.
        $this->assertDatabaseHas('social_post_targets', [
            'social_post_id'    => $copia->id,
            'social_account_id' => $account->id,
            'status'            => SocialPostTarget::STATUS_PENDING,
        ]);

        // El original no se toca.
        $this->assertSame(SocialPost::STATUS_PUBLISHED, $post->fresh()->status);
    }

    public function test_no_se_puede_duplicar_el_post_de_otro_cliente(): void
    {
        [, $post] = $this->escenario();
        $ajeno = Client::create(['business_name' => 'Otro']);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$ajeno->id}/posts/{$post->id}/duplicate")
            ->assertNotFound();

        $this->assertSame(1, SocialPost::count());
    }

    public function test_editar_un_post_publicado_sigue_rechazandose(): void
    {
        [$client, $post] = $this->escenario();

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts/{$post->id}", [
                'body'        => 'Intento de cambio',
                'account_ids' => [],
                'action'      => 'save_draft',
            ])
            ->assertStatus(422);

        $this->assertSame('Contenido publicado', $post->fresh()->body);
    }
}
