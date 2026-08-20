<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El calendario coloca cada post con COALESCE(scheduled_at, published_at,
 * created_at). El filtro del mes tiene que usar esa MISMA fecha.
 *
 * Antes el backend recortaba por `COALESCE(scheduled_at, created_at)` y el
 * frontend pintaba por `scheduled_at || published_at`: un post publicado al
 * momento —sin `scheduled_at`— entraba en el payload del mes pero no caía en
 * ningún día, y si además una red fallaba tampoco tenía `published_at`, así
 * que desaparecía por completo de la vista de calendario.
 */
class SocialCalendarDateTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $staff = User::factory()->create(['client_id' => null, 'email_verified_at' => now()]);
        $staff->assignRole(Role::findOrCreate(config('roles.admin'), 'web'));

        return $staff;
    }

    public function test_un_post_publicado_al_momento_entra_en_el_mes_en_curso(): void
    {
        $client = Client::create(['business_name' => 'Demo']);

        // Ni programado ni terminado: es el estado en el que queda un post
        // "publicar ahora" mientras corre el job, y en el que se queda para
        // siempre si una de las redes falla (`partial`).
        $post = SocialPost::create([
            'client_id'    => $client->id,
            'body'         => 'Publicado ahora',
            'status'       => SocialPost::STATUS_PARTIAL,
            'scheduled_at' => null,
            'published_at' => null,
        ]);

        $this->actingAs($this->staff())
            ->get("/social/clients/{$client->id}?month=" . now()->format('Y-m'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('posts.0.id', $post->id));
    }

    public function test_el_mes_se_toma_de_published_at_cuando_no_hay_programacion(): void
    {
        $client = Client::create(['business_name' => 'Demo']);

        // Creado el mes pasado, publicado este: pertenece al mes en el que
        // salió a las redes, no al del borrador.
        $post = SocialPost::create([
            'client_id'    => $client->id,
            'body'         => 'Borrador viejo publicado hoy',
            'status'       => SocialPost::STATUS_PUBLISHED,
            'scheduled_at' => null,
            'published_at' => now(),
        ]);
        $post->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();

        $this->actingAs($this->staff())
            ->get("/social/clients/{$client->id}?month=" . now()->format('Y-m'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('posts.0.id', $post->id));
    }
}
