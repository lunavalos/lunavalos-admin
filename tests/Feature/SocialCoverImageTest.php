<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\User;
use App\Services\SocialPublishing\Drivers\FacebookPublisher;
use App\Services\SocialPublishing\Drivers\InstagramPublisher;
use App\Services\SocialPublishing\Drivers\TikTokPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La portada del video (reels, shorts, videos de Facebook).
 *
 * Vive dentro de `media` con role=cover, así que lo primero que tiene que
 * quedar amarrado es que nunca se publique como contenido: un reel con la
 * portada de `media[0]` publicaría la imagen en lugar del video.
 */
class SocialCoverImageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        return $user;
    }

    private function target(string $provider, array $media, array $options = []): SocialPostTarget
    {
        $client = Client::create(['business_name' => 'Demo']);

        $account = SocialAccount::create([
            'client_id'        => $client->id,
            'provider'         => $provider,
            'provider_user_id' => '123',
            'name'             => 'Cuenta demo',
            'access_token'     => 'token-demo',
            'meta'             => ['page_id' => '123', 'ig_business_id' => '456'],
        ]);

        $post = SocialPost::create([
            'client_id' => $client->id,
            'body'      => 'Contenido de prueba',
            'media'     => $media,
            'options'   => $options,
            'status'    => SocialPost::STATUS_DRAFT,
        ]);

        return SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'provider'          => $provider,
            'status'            => SocialPostTarget::STATUS_PENDING,
        ]);
    }

    private function reelConPortada(string $provider, array $options = []): SocialPostTarget
    {
        return $this->target($provider, [
            ['path' => 'social/reel.mp4',    'mime' => 'video/mp4',  'name' => 'reel.mp4'],
            ['path' => 'social/portada.jpg', 'mime' => 'image/jpeg', 'name' => 'portada.jpg', 'role' => 'cover'],
        ], $options);
    }

    private function sinDormir(): InstagramPublisher
    {
        return new class extends InstagramPublisher {
            protected function dormir(int $segundos): void {}
        };
    }

    public function test_instagram_manda_la_portada_como_cover_url_y_publica_el_video(): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => 'cont_1'], 200),
            '*/cont_1*'           => Http::response(['status_code' => 'FINISHED'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_1'], 200),
            '*/ig_1*'             => Http::response(['permalink' => 'https://www.instagram.com/reel/ABC/'], 200),
        ]);

        $target = $this->sinDormir()->publish($this->reelConPortada('instagram'));

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);

        Http::assertSent(function ($r) {
            if (!str_contains($r->url(), '/456/media')) return false;

            // El video sigue siendo el contenido; la portada solo la carátula.
            return ($r['media_type'] ?? null) === 'REELS'
                && str_contains($r['video_url'] ?? '', 'reel.mp4')
                && str_contains($r['cover_url'] ?? '', 'portada.jpg')
                && !isset($r['image_url'])
                // cover_url y thumb_offset juntos hacen que Meta ignore uno.
                && !isset($r['thumb_offset']);
        });
    }

    public function test_instagram_usa_el_segundo_elegido_cuando_no_hay_portada(): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => 'cont_2'], 200),
            '*/cont_2*'           => Http::response(['status_code' => 'FINISHED'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_2'], 200),
            '*/ig_2*'             => Http::response(['permalink' => 'https://www.instagram.com/reel/DEF/'], 200),
        ]);

        $target = $this->target(
            'instagram',
            [['path' => 'social/reel.mp4', 'mime' => 'video/mp4', 'name' => 'reel.mp4']],
            ['cover_timestamp_ms' => 2500],
        );

        $this->sinDormir()->publish($target);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media')
            && ($r['thumb_offset'] ?? null) == 2500
            && !isset($r['cover_url']));
    }

    public function test_facebook_sube_la_portada_como_miniatura_preferida(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('social/portada.jpg', 'bytes-de-la-portada');

        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'vid_1'], 200)]);

        $target = (new FacebookPublisher())->publish($this->reelConPortada('facebook'));

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);

        // El video va por /videos con la URL del mp4, no con la de la portada.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/videos')
            && str_contains($r['file_url'] ?? '', 'reel.mp4'));

        // La miniatura solo se puede mandar después, como archivo.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/vid_1/thumbnails'));
    }

    public function test_facebook_publica_aunque_falle_la_miniatura(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('social/portada.jpg', 'bytes-de-la-portada');

        Http::fake([
            '*/vid_1/thumbnails' => Http::response(['error' => ['message' => 'nope']], 400),
            'graph.facebook.com/*' => Http::response(['id' => 'vid_1'], 200),
        ]);

        $target = (new FacebookPublisher())->publish($this->reelConPortada('facebook'));

        // El video ya salió: quedarse con la miniatura automática no es un fallo.
        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertSame('vid_1', $target->platform_post_id);
        $this->assertNull($target->error_message);
    }

    public function test_tiktok_usa_el_segundo_de_caratula_elegido(): void
    {
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['publish_id' => 'pub_1']], 200)]);

        $target = $this->target(
            'tiktok',
            [['path' => 'social/reel.mp4', 'mime' => 'video/mp4', 'name' => 'reel.mp4']],
            ['cover_timestamp_ms' => 3000],
        );

        (new TikTokPublisher())->publish($target);

        // TikTok no acepta imagen de portada, solo el timestamp.
        Http::assertSent(fn ($r) => ($r['post_info']['video_cover_timestamp_ms'] ?? null) === 3000);
    }

    public function test_la_portada_se_guarda_aparte_del_contenido(): void
    {
        Storage::fake('public');

        $client  = Client::create(['business_name' => 'Demo']);
        $account = SocialAccount::create([
            'client_id' => $client->id, 'provider' => 'instagram',
            'provider_user_id' => '1', 'name' => 'IG', 'access_token' => 'tok',
        ]);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts", [
                'account_ids' => [$account->id],
                'action'      => 'save_draft',
                'body'        => 'Un reel',
                'media'       => [UploadedFile::fake()->create('reel.mp4', 100, 'video/mp4')],
                'cover'       => UploadedFile::fake()->image('portada.jpg'),
            ])
            ->assertRedirect();

        $post = SocialPost::firstOrFail();

        $this->assertCount(1, $post->mediaPrincipal());
        $this->assertStringEndsWith('.mp4', $post->mediaPrincipal()[0]['path']);
        $this->assertNotNull($post->portada());
        $this->assertSame(SocialPost::ROLE_COVER, $post->portada()['role']);
    }

    public function test_editar_el_texto_no_borra_la_portada_ni_el_video(): void
    {
        Storage::fake('public');

        $client  = Client::create(['business_name' => 'Demo']);
        $account = SocialAccount::create([
            'client_id' => $client->id, 'provider' => 'instagram',
            'provider_user_id' => '1', 'name' => 'IG', 'access_token' => 'tok',
        ]);
        $post = SocialPost::create([
            'client_id' => $client->id,
            'body'      => 'Original',
            'media'     => [
                ['path' => 'social/reel.mp4',    'mime' => 'video/mp4',  'name' => 'reel.mp4'],
                ['path' => 'social/portada.jpg', 'mime' => 'image/jpeg', 'name' => 'portada.jpg', 'role' => 'cover'],
            ],
            'status'    => SocialPost::STATUS_DRAFT,
        ]);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts/{$post->id}", [
                'account_ids' => [$account->id],
                'action'      => 'save_draft',
                'body'        => 'Texto corregido',
            ])
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('Texto corregido', $post->body);
        $this->assertSame('social/reel.mp4', $post->mediaPrincipal()[0]['path']);
        $this->assertSame('social/portada.jpg', $post->portada()['path']);
    }

    public function test_cambiar_solo_el_video_conserva_la_portada(): void
    {
        Storage::fake('public');

        $client  = Client::create(['business_name' => 'Demo']);
        $account = SocialAccount::create([
            'client_id' => $client->id, 'provider' => 'instagram',
            'provider_user_id' => '1', 'name' => 'IG', 'access_token' => 'tok',
        ]);
        $post = SocialPost::create([
            'client_id' => $client->id,
            'media'     => [
                ['path' => 'social/viejo.mp4',   'mime' => 'video/mp4',  'name' => 'viejo.mp4'],
                ['path' => 'social/portada.jpg', 'mime' => 'image/jpeg', 'name' => 'portada.jpg', 'role' => 'cover'],
            ],
            'status'    => SocialPost::STATUS_DRAFT,
        ]);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts/{$post->id}", [
                'account_ids' => [$account->id],
                'action'      => 'save_draft',
                'media'       => [UploadedFile::fake()->create('nuevo.mp4', 100, 'video/mp4')],
            ])
            ->assertRedirect();

        $post->refresh();

        $this->assertCount(1, $post->mediaPrincipal());
        $this->assertStringContainsString('.mp4', $post->mediaPrincipal()[0]['path']);
        $this->assertNotSame('social/viejo.mp4', $post->mediaPrincipal()[0]['path']);
        $this->assertSame('social/portada.jpg', $post->portada()['path']);
    }

    public function test_se_puede_quitar_la_portada(): void
    {
        Storage::fake('public');

        $client  = Client::create(['business_name' => 'Demo']);
        $account = SocialAccount::create([
            'client_id' => $client->id, 'provider' => 'instagram',
            'provider_user_id' => '1', 'name' => 'IG', 'access_token' => 'tok',
        ]);
        $post = SocialPost::create([
            'client_id' => $client->id,
            'media'     => [
                ['path' => 'social/reel.mp4',    'mime' => 'video/mp4',  'name' => 'reel.mp4'],
                ['path' => 'social/portada.jpg', 'mime' => 'image/jpeg', 'name' => 'portada.jpg', 'role' => 'cover'],
            ],
            'status'    => SocialPost::STATUS_DRAFT,
        ]);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts/{$post->id}", [
                'account_ids'  => [$account->id],
                'action'       => 'save_draft',
                'remove_cover' => true,
            ])
            ->assertRedirect();

        $post->refresh();

        $this->assertNull($post->portada());
        $this->assertCount(1, $post->mediaPrincipal());
    }

    public function test_la_portada_tiene_que_ser_una_imagen(): void
    {
        Storage::fake('public');

        $client  = Client::create(['business_name' => 'Demo']);
        $account = SocialAccount::create([
            'client_id' => $client->id, 'provider' => 'instagram',
            'provider_user_id' => '1', 'name' => 'IG', 'access_token' => 'tok',
        ]);

        $this->actingAs($this->staff())
            ->post("/social/clients/{$client->id}/posts", [
                'account_ids' => [$account->id],
                'action'      => 'save_draft',
                'cover'       => UploadedFile::fake()->create('portada.mp4', 10, 'video/mp4'),
            ])
            ->assertSessionHasErrors('cover');
    }
}
