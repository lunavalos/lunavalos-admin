<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\SocialPublishing\Drivers\FacebookPublisher;
use App\Services\SocialPublishing\Drivers\InstagramPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Meta usa endpoints y parámetros distintos para foto y video. Mandar un .mp4
 * por la vía de imagen falla con errores que nunca mencionan el video:
 *   Pages     -> 400 "Invalid parameter", subcode 1366046
 *   Instagram -> 500 "Please reduce the amount of data you're asking for"
 * Los dos se vieron en producción al subir un reel.
 */
class SocialVideoPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function target(string $provider, string $archivo, string $mime): SocialPostTarget
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
            'media'     => [['path' => $archivo, 'mime' => $mime, 'name' => basename($archivo)]],
            'status'    => SocialPost::STATUS_DRAFT,
        ]);

        return SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'provider'          => $provider,
            'status'            => SocialPostTarget::STATUS_PENDING,
        ]);
    }

    public function test_facebook_manda_el_video_a_videos_y_no_a_photos(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'vid_1'], 200)]);

        $target = (new FacebookPublisher())->publish(
            $this->target('facebook', 'social/reel.mp4', 'video/mp4')
        );

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertSame('vid_1', $target->platform_post_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/videos') && isset($r['file_url']));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/photos'));
    }

    public function test_facebook_sigue_usando_photos_para_imagenes(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['post_id' => 'pic_1'], 200)]);

        (new FacebookPublisher())->publish(
            $this->target('facebook', 'social/foto.jpg', 'image/jpeg')
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/photos'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/videos'));
    }

    public function test_instagram_publica_el_video_como_reel_y_espera_el_procesado(): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => 'cont_1'], 200),
            '*/cont_1*'           => Http::response(['status_code' => 'FINISHED'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_1'], 200),
            '*/ig_1*'             => Http::response(['permalink' => 'https://www.instagram.com/reel/ABC123/'], 200),
        ]);

        $publisher = new class extends InstagramPublisher {
            protected function dormir(int $segundos): void {}
        };

        $target = $publisher->publish($this->target('instagram', 'social/reel.mp4', 'video/mp4'));

        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertSame('ig_1', $target->platform_post_id);
        // El permalink se pide a Meta: armar la URL con el id numérico da 404.
        $this->assertSame('https://www.instagram.com/reel/ABC123/', $target->platform_url);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media')
            && ($r['media_type'] ?? null) === 'REELS'
            && isset($r['video_url']));

        // El sondeo del contenedor es obligatorio: publicar antes de que Meta
        // termine de transcodificar falla.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'cont_1') && $r->method() === 'GET');
    }

    public function test_instagram_falla_con_mensaje_claro_si_meta_rechaza_el_video(): void
    {
        Http::fake([
            '*/456/media' => Http::response(['id' => 'cont_1'], 200),
            '*/cont_1*'   => Http::response(['status_code' => 'ERROR', 'status' => 'Formato no soportado'], 200),
        ]);

        $publisher = new class extends InstagramPublisher {
            protected function dormir(int $segundos): void {}
        };

        $target = $publisher->publish($this->target('instagram', 'social/reel.mp4', 'video/mp4'));

        $this->assertSame(SocialPostTarget::STATUS_FAILED, $target->status);
        $this->assertStringContainsString('Formato no soportado', $target->error_message);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'media_publish'));
    }

    public function test_instagram_sigue_usando_image_url_para_imagenes(): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => 'cont_2'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_2'], 200),
            '*/ig_2*'             => Http::response(['permalink' => 'https://www.instagram.com/p/XYZ789/'], 200),
        ]);

        (new InstagramPublisher())->publish($this->target('instagram', 'social/foto.jpg', 'image/jpeg'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/456/media') && isset($r['image_url']));
        // Sin sondeo: las imágenes quedan listas al instante.
        Http::assertNotSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'cont_2'));
    }

    public function test_instagram_publica_aunque_no_se_pueda_obtener_el_permalink(): void
    {
        Http::fake([
            '*/456/media'         => Http::response(['id' => 'cont_3'], 200),
            '*/456/media_publish' => Http::response(['id' => 'ig_3'], 200),
            '*/ig_3*'             => Http::response(['error' => ['message' => 'nope']], 400),
        ]);

        $target = (new InstagramPublisher())->publish($this->target('instagram', 'social/foto.jpg', 'image/jpeg'));

        // Ya salió publicado: quedarse sin enlace no lo invalida.
        $this->assertSame(SocialPostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertSame('ig_3', $target->platform_post_id);
        $this->assertNull($target->platform_url);
    }
}
