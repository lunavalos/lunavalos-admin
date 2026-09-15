<?php

namespace Tests\Feature;

use App\Jobs\PublishSocialPostJob;
use App\Models\Client;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `social:dispatch-scheduled` corre cada 5 minutos, pero publicar en una red
 * puede tardar más que eso (subir un video a Instagram, por ejemplo) o la cola
 * puede ir retrasada. Mientras el post siguiera en `scheduled` la siguiente
 * corrida lo volvía a encolar y la red recibía la publicación duplicada.
 *
 * El comando ahora reclama el post —lo pasa a `publishing`— antes de encolarlo.
 */
class SocialScheduledDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function postProgramado(\DateTimeInterface|string $cuando): SocialPost
    {
        $client = Client::create(['business_name' => 'Demo']);

        return SocialPost::create([
            'client_id'    => $client->id,
            'body'         => 'Programado',
            'status'       => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => $cuando,
        ]);
    }

    public function test_encola_el_post_cuya_hora_ya_paso(): void
    {
        Queue::fake();

        $post = $this->postProgramado(now()->subMinute());

        $this->artisan('social:dispatch-scheduled')->assertSuccessful();

        Queue::assertPushed(PublishSocialPostJob::class, fn ($job) => $job->socialPostId === $post->id);
        $this->assertSame(SocialPost::STATUS_PUBLISHING, $post->fresh()->status);
    }

    public function test_no_encola_el_post_cuya_hora_no_ha_llegado(): void
    {
        Queue::fake();

        $post = $this->postProgramado(now()->addHour());

        $this->artisan('social:dispatch-scheduled')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(SocialPost::STATUS_SCHEDULED, $post->fresh()->status);
    }

    public function test_no_encola_dos_veces_el_mismo_post(): void
    {
        Queue::fake();

        $this->postProgramado(now()->subMinute());

        $this->artisan('social:dispatch-scheduled')->assertSuccessful();
        // Segunda corrida cinco minutos después, con el job todavía en la cola.
        $this->artisan('social:dispatch-scheduled')->assertSuccessful();

        Queue::assertPushed(PublishSocialPostJob::class, 1);
    }
}
