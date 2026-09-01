<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\SocialPublishing\Drivers\FacebookPublisher;
use App\Services\SocialPublishing\Drivers\InstagramPublisher;
use App\Services\SocialPublishing\Drivers\LinkedInPublisher;
use App\Services\SocialPublishing\Drivers\TikTokPublisher;
use App\Services\SocialPublishing\Drivers\YouTubePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Las opciones por red del compositor.
 *
 * Se validaban y se guardaban, pero ningún publisher las leía: elegir "Story"
 * publicaba al feed, "Reel" de Facebook salía como video normal, "Enviar a
 * borradores" publicaba directo al perfil y el post con imagen de LinkedIn
 * salía sin la imagen. Todo eso sin un solo error: la interfaz prometía cosas
 * que no pasaban.
 */
class SocialPostOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function target(string $provider, array $media, array $options): SocialPostTarget
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
            'title'     => 'Título',
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

    private function video(): array
    {
        return [['path' => 'social/reel.mp4', 'mime' => 'video/mp4', 'name' => 'reel.mp4']];
    }

    private function imagen(): array
    {
        return [['path' => 'social/foto.jpg', 'mime' => 'image/jpeg', 'name' => 'foto.jpg']];
    }

    private function igSinDormir(): InstagramPublisher
    {
        return new class extends InstagramPublisher {
            protected function dormir(int $segundos): void {}
        };
    }

    private function fakeInstagram(string $contenedor = 'cont_1'): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => $contenedor], 200),
            "*/{$contenedor}*"    => Http::response(['status_code' => 'FINISHED'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_1'], 200),
            '*/ig_1*'             => Http::response(['permalink' => 'https://instagram.com/p/A/'], 200),
        ]);
    }

    // ── Instagram ──────────────────────────────────────────────────────────

    public function test_instagram_story_usa_su_propio_media_type(): void
    {
        $this->fakeInstagram();

        $target = $this->target('instagram', $this->imagen(), ['instagram_type' => 'story']);
        $this->assertSame(
            SocialPostTarget::STATUS_PUBLISHED,
            $this->igSinDormir()->publish($target)->status,
        );

        // Sin STORIES la "story" quedaba fija en el perfil como un post normal.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media')
            && ($r['media_type'] ?? null) === 'STORIES'
            && isset($r['image_url'])
            // Instagram ignora el caption en las stories.
            && !isset($r['caption']));
    }

    public function test_instagram_story_de_video_manda_video_url(): void
    {
        $this->fakeInstagram('cont_2');

        $target = $this->target('instagram', $this->video(), ['instagram_type' => 'story']);
        $this->igSinDormir()->publish($target);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media')
            && ($r['media_type'] ?? null) === 'STORIES'
            && isset($r['video_url']));
    }

    public function test_instagram_feed_con_imagen_sigue_igual(): void
    {
        $this->fakeInstagram('cont_3');

        $target = $this->target('instagram', $this->imagen(), ['instagram_type' => 'feed']);
        $this->igSinDormir()->publish($target);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media')
            && isset($r['image_url'])
            && !isset($r['media_type']));
    }

    // ── Facebook ───────────────────────────────────────────────────────────

    public function test_facebook_reel_usa_la_api_de_reels_y_no_videos(): void
    {
        Http::fake([
            '*/video_reels'      => Http::response(['video_id' => 'reel_1', 'upload_url' => 'https://rupload.facebook.com/video-upload/v19.0/reel_1'], 200),
            'rupload.facebook.com/*' => Http::response(['success' => true], 200),
            'graph.facebook.com/*'   => Http::response(['id' => 'x'], 200),
        ]);

        $target = (new FacebookPublisher())->publish(
            $this->target('facebook', $this->video(), ['facebook_type' => 'reel']),
        );

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertSame('reel_1', $target->platform_post_id);

        // Las tres fases: reservar, subir y publicar.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/video_reels') && ($r['upload_phase'] ?? null) === 'start');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'rupload.facebook.com')
            && $r->hasHeader('file_url'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/video_reels')
            && ($r['upload_phase'] ?? null) === 'finish'
            && ($r['video_state'] ?? null) === 'PUBLISHED');

        // Un reel por /videos queda como video normal del feed.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/123/videos'));
    }

    public function test_facebook_texto_no_adjunta_la_imagen_del_post(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'p_1'], 200)]);

        (new FacebookPublisher())->publish(
            $this->target('facebook', $this->imagen(), ['facebook_type' => 'post']),
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/feed'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/photos'));
    }

    public function test_facebook_deduce_el_formato_en_posts_sin_opciones(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'v_1'], 200)]);

        // Los posts guardados antes del selector no traen `facebook_type`.
        (new FacebookPublisher())->publish($this->target('facebook', $this->video(), []));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/videos'));
    }

    // ── TikTok ─────────────────────────────────────────────────────────────

    public function test_tiktok_respeta_privacidad_e_interacciones(): void
    {
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['publish_id' => 'p_1']], 200)]);

        (new TikTokPublisher())->publish($this->target('tiktok', $this->video(), [
            'tiktok_privacy'         => 'SELF_ONLY',
            'tiktok_disable_comment' => true,
            'tiktok_disable_duet'    => true,
            'tiktok_disable_stitch'  => false,
        ]));

        Http::assertSent(function ($r) {
            $info = $r['post_info'] ?? [];

            // Antes iba todo fijo: público y con las tres interacciones abiertas.
            return ($info['privacy_level'] ?? null) === 'SELF_ONLY'
                && ($info['disable_comment'] ?? null) === true
                && ($info['disable_duet'] ?? null) === true
                && ($info['disable_stitch'] ?? null) === false;
        });
    }

    public function test_tiktok_borrador_va_al_inbox_y_no_al_perfil(): void
    {
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['publish_id' => 'p_2']], 200)]);

        $target = (new TikTokPublisher())->publish(
            $this->target('tiktok', $this->video(), ['tiktok_type' => 'draft']),
        );

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/inbox/video/init/'));
        // El borrador no puede salir por el endpoint que publica directo.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/post/publish/video/init/'));
    }

    // ── YouTube ────────────────────────────────────────────────────────────

    public function test_youtube_marca_los_shorts_en_la_descripcion(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('social/reel.mp4', 'bytes');
        Http::fake(['googleapis.com/*' => Http::response(['id' => 'yt_1'], 200)]);

        (new YouTubePublisher())->publish(
            $this->target('youtube', $this->video(), ['youtube_type' => 'short']),
        );

        // La API no tiene campo para Shorts: la etiqueta en la descripción es
        // la única señal que se le puede mandar.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/videos')
            && str_contains($r->body(), '#Shorts'));
    }

    public function test_youtube_no_toca_la_descripcion_de_un_video_normal(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('social/reel.mp4', 'bytes');
        Http::fake(['googleapis.com/*' => Http::response(['id' => 'yt_2'], 200)]);

        (new YouTubePublisher())->publish(
            $this->target('youtube', $this->video(), ['youtube_type' => 'video']),
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), '/videos')
            && !str_contains($r->body(), '#Shorts'));
    }

    // ── LinkedIn ───────────────────────────────────────────────────────────

    public function test_linkedin_sube_la_imagen_y_la_referencia_en_el_post(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('social/foto.jpg', 'bytes-de-la-foto');

        Http::fake([
            '*/assets?action=registerUpload' => Http::response(['value' => [
                'asset' => 'urn:li:digitalmediaAsset:ABC',
                'uploadMechanism' => [
                    'com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest' => [
                        'uploadUrl' => 'https://upload.linkedin.com/asset/ABC',
                    ],
                ],
            ]], 200),
            'upload.linkedin.com/*' => Http::response('', 201),
            '*/ugcPosts'            => Http::response(['id' => 'urn:li:share:1'], 200),
        ]);

        $target = (new LinkedInPublisher())->publish($this->target('linkedin', $this->imagen(), [
            'linkedin_type'     => 'image',
            'linkedin_alt_text' => 'Equipo en la oficina',
        ]));

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'registerUpload'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'upload.linkedin.com'));

        // El post cita el asset: antes salía como texto plano, sin la imagen.
        Http::assertSent(function ($r) {
            if (!str_contains($r->url(), 'ugcPosts')) return false;
            $contenido = $r['specificContent']['com.linkedin.ugc.ShareContent'] ?? [];

            return ($contenido['shareMediaCategory'] ?? null) === 'IMAGE'
                && ($contenido['media'][0]['media'] ?? null) === 'urn:li:digitalmediaAsset:ABC'
                && ($contenido['media'][0]['description']['text'] ?? null) === 'Equipo en la oficina';
        });
    }

    public function test_linkedin_comparte_el_articulo_con_su_url(): void
    {
        Http::fake(['*/ugcPosts' => Http::response(['id' => 'urn:li:share:2'], 200)]);

        (new LinkedInPublisher())->publish($this->target('linkedin', [], [
            'linkedin_type'        => 'article',
            'linkedin_article_url' => 'https://lunavalos.com/blog/post',
        ]));

        Http::assertSent(function ($r) {
            $contenido = $r['specificContent']['com.linkedin.ugc.ShareContent'] ?? [];

            return ($contenido['shareMediaCategory'] ?? null) === 'ARTICLE'
                && ($contenido['media'][0]['originalUrl'] ?? null) === 'https://lunavalos.com/blog/post';
        });
    }

    public function test_linkedin_de_texto_sigue_saliendo_sin_media(): void
    {
        Http::fake(['*/ugcPosts' => Http::response(['id' => 'urn:li:share:3'], 200)]);

        (new LinkedInPublisher())->publish($this->target('linkedin', [], ['linkedin_type' => 'text']));

        Http::assertSent(function ($r) {
            $contenido = $r['specificContent']['com.linkedin.ugc.ShareContent'] ?? [];

            return ($contenido['shareMediaCategory'] ?? null) === 'NONE'
                && !isset($contenido['media']);
        });
    }

    public function test_linkedin_falla_con_mensaje_claro_si_el_articulo_no_trae_url(): void
    {
        Http::fake(['*/ugcPosts' => Http::response(['id' => 'urn:li:share:4'], 200)]);

        $target = (new LinkedInPublisher())->publish(
            $this->target('linkedin', [], ['linkedin_type' => 'article']),
        );

        $this->assertSame(SocialPostTarget::STATUS_FAILED, $target->status);
        $this->assertStringContainsString('requiere la URL', $target->error_message);
        Http::assertNothingSent();
    }
}
