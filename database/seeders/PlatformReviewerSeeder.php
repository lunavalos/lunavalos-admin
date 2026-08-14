<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crea la cuenta que se entrega a los revisores de plataforma (Meta App
 * Review, y en su momento LinkedIn y TikTok) junto con el cliente ficticio
 * que verán al entrar.
 *
 * Puede correr en producción porque la cuenta queda amarrada al cliente demo
 * vía `users.client_id`: SocialController y SocialAuthController acotan a ese
 * cliente, así que el revisor no ve a los demás ni puede publicar en sus
 * páginas. Ese amarre es la protección — si se rompe, se filtran datos de
 * clientes reales, y por eso se verifica al final.
 *
 *   REVIEWER_PASSWORD=...  php artisan db:seed --class=PlatformReviewerSeeder
 *
 * Es idempotente: correrlo de nuevo actualiza la contraseña y deja el resto
 * como está.
 */
class PlatformReviewerSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('REVIEWER_PASSWORD', '');
        if ($password === '') {
            throw new RuntimeException(
                'Falta REVIEWER_PASSWORD. Defínela antes de correr este seeder; '
                . 'no se genera una contraseña por omisión a propósito.'
            );
        }

        $role   = $this->reviewerRole();
        $client = $this->demoClient();

        $user = User::updateOrCreate(
            ['email' => env('REVIEWER_EMAIL', 'platform-reviewer@lunavalos.com')],
            [
                'name'              => 'Platform Reviewer',
                'password'          => Hash::make($password),
                // Las rutas del panel exigen `verified`; sin esto el revisor
                // queda atorado en la pantalla de verificación de correo.
                'email_verified_at' => now(),
                // El amarre que lo mantiene dentro del cliente demo.
                'client_id'         => $client->id,
            ]
        );

        $user->syncRoles([$role]);

        $this->demoWhatsappTicket($client, $user);

        // Sin client_id la cuenta vería a todos los clientes reales. Es la
        // única invariante que protege los datos: se verifica, no se supone.
        $user->refresh();
        if ($user->client_id !== $client->id) {
            throw new RuntimeException(
                'La cuenta de revisión quedó sin client_id. Abortando: sin ese amarre '
                . 'el revisor vería todos los clientes. Revisa que `client_id` esté en $fillable.'
            );
        }

        $this->command?->info("Cuenta de revisión lista: {$user->email}");
        $this->command?->info("Acotada al cliente demo: {$client->business_name} (id {$client->id})");
    }

    /**
     * Solo `Ver Social`: es el flujo que el revisor tiene que recorrer.
     *
     * A propósito NO lleva `Ver Tickets`. `TicketController::index()` acota por
     * el rol Cliente, no por `client_id`, así que esa cuenta vería las
     * conversaciones de WhatsApp de todos los clientes. El flujo de WhatsApp se
     * demuestra con screencast.
     */
    private function reviewerRole(): Role
    {
        $role = Role::firstOrCreate([
            'name'       => config('roles.reviewer', 'Revisor de Plataforma'),
            'guard_name' => 'web',
        ]);

        $permisos = collect(['Ver Dashboard', 'Ver Social'])
            ->map(fn (string $nombre) => Permission::firstOrCreate([
                'name'       => $nombre,
                'guard_name' => 'web',
            ]));

        $role->syncPermissions($permisos);

        return $role;
    }

    private function demoClient(): Client
    {
        return Client::updateOrCreate(
            ['business_name' => 'Demo Coffee Roasters'],
            [
                'contact_name' => 'Ana Demo',
                'email'        => 'demo@example.com',
                'phone'        => '5215500000000',
                'city'         => 'Saltillo',
            ]
        );
    }

    /**
     * Un ticket de WhatsApp ficticio para que el revisor vea el flujo de
     * atención sin abrir la conversación de ningún cliente real.
     */
    private function demoWhatsappTicket(Client $client, User $creator): void
    {
        $ticket = Ticket::firstOrCreate(
            ['whatsapp_wa_id' => '5215500000001'],
            [
                'title'             => 'WhatsApp · Demo Coffee Roasters',
                'content'           => 'Hi! Do you deliver on Saturdays?',
                'priority'          => 'Media',
                'status'            => 'Nuevos',
                'source_type'       => Ticket::SOURCE_SUPPORT,
                'channel'           => Ticket::CHANNEL_WHATSAPP,
                'creator_id'        => $creator->id,
                'client_id'         => $client->id,
                'visible_to_client' => true,
            ]
        );

        $mensajes = [
            ['wamid.DEMO_IN_1',  'Hi! Do you deliver on Saturdays?',                      TicketMessage::DIRECTION_IN],
            ['wamid.DEMO_OUT_1', 'Hello! Yes, we deliver Saturdays from 9am to 2pm.',     TicketMessage::DIRECTION_OUT],
        ];

        foreach ($mensajes as [$waId, $texto, $direccion]) {
            TicketMessage::firstOrCreate(
                ['wa_message_id' => $waId],
                [
                    'ticket_id' => $ticket->id,
                    'user_id'   => $direccion === TicketMessage::DIRECTION_OUT ? $creator->id : null,
                    'message'   => $texto,
                    'channel'   => Ticket::CHANNEL_WHATSAPP,
                    'direction' => $direccion,
                ]
            );
        }
    }
}
