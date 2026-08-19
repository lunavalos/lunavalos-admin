<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
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

        // Dos roles a propósito:
        //
        //   Cliente  -> TicketController acota por ESE rol, no por client_id, y
        //               el módulo de Tickets es visible para todo el mundo (no
        //               hay permiso que lo cubra). Sin este rol, la cuenta de
        //               revisión ve TODOS los tickets, incluidas conversaciones
        //               de WhatsApp de clientes finales de otros clientes.
        //   Revisor  -> exime del 2FA obligatorio y concede Ver Social.
        //
        // Se reutiliza el acotamiento que ya existe y está probado en vez de
        // tocar los 17 puntos donde TicketController pregunta por el rol.
        $user->syncRoles([
            $role,
            Role::firstOrCreate(['name' => config('roles.client', 'Cliente'), 'guard_name' => 'web']),
        ]);

        $this->demoWhatsappTicket($client, $user);
        $this->demoWhatsappConversation($client, $user);

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
     * Lo mínimo para recorrer el flujo descrito en las instrucciones de prueba.
     *
     * `Ver Tickets` es informativo: el módulo de Tickets no está protegido por
     * ningún permiso —el enlace del sidebar es visible para todos—, así que lo
     * que de verdad protege esos datos es el rol Cliente que se asigna abajo.
     *
     * `Gestionar Social` y `Publicar Social` sí son necesarios: el revisor tiene
     * que conectar una página y publicar para demostrar el permiso que Meta está
     * evaluando. Antes le bastaba con estar autenticado porque las rutas de
     * social no validaban permisos; ahora que sí lo hacen, hay que declararlos.
     * El alcance sigue acotado a su cliente demo vía `users.client_id`.
     */
    private function reviewerRole(): Role
    {
        $role = Role::firstOrCreate([
            'name'       => config('roles.reviewer', 'Revisor de Plataforma'),
            'guard_name' => 'web',
        ]);

        $permisos = collect([
            'Ver Dashboard',
            'Ver Social',
            'Gestionar Social',
            'Publicar Social',
            'Ver Tickets',
            // WhatsApp. Sin estos, el revisor entra y no puede evaluar ninguno
            // de los dos permisos que Meta está revisando:
            //   Ver/Responder Conversaciones  -> whatsapp_business_messaging
            //   Gestionar Plantillas WhatsApp -> whatsapp_business_management
            // 'Gestionar WhatsApp' le deja ver la pantalla de conexión. Los
            // cuatro siguen acotados al cliente demo por `users.client_id`.
            'Ver Conversaciones',
            'Responder Conversaciones',
            'Gestionar WhatsApp',
            'Gestionar Plantillas WhatsApp',
        ])
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
    /**
     * El módulo de Conversaciones con datos ficticios.
     *
     * El ticket de arriba es el modelo anterior y se conserva por el histórico.
     * Lo que el revisor necesita ver para evaluar los permisos de WhatsApp es
     * esto: la bandeja con un hilo real y la pantalla de plantillas con algo
     * dentro. Sin fixtures, ambas se ven vacías y no hay nada que evaluar.
     *
     * La WABA demo lleva un token de relleno —no null— a propósito:
     * `WhatsAppAccount::tokenParaEnviar()` cae al token de producción cuando la
     * columna está vacía, así que dejarla nula haría que un intento de respuesta
     * del revisor saliera con NUESTRAS credenciales reales. Con el relleno, el
     * envío falla contra Graph y no toca nada de producción.
     */
    private function demoWhatsappConversation(Client $client, User $revisor): void
    {
        $cuenta = WhatsAppAccount::updateOrCreate(
            ['waba_id' => 'DEMO-WABA-0001'],
            [
                'name'         => 'Demo Coffee Roasters',
                'access_token' => 'demo-token-sin-uso',
                'status'       => WhatsAppAccount::STATUS_ACTIVE,
            ],
        );

        $numero = WhatsAppNumber::updateOrCreate(
            ['phone_number_id' => 'DEMO-PHONE-0001'],
            [
                'whatsapp_account_id'  => $cuenta->id,
                'client_id'            => $client->id,
                'display_phone_number' => '+52 155 0000 0000',
                'verified_name'        => 'Demo Coffee Roasters',
                'quality_rating'       => 'GREEN',
                'is_active'            => true,
            ],
        );

        $conversacion = Conversation::updateOrCreate(
            [
                'whatsapp_number_id' => $numero->id,
                'contact_wa_id'      => '5215500000001',
            ],
            [
                'client_id'    => $client->id,
                'contact_name' => 'Ana Demo',
                'status'       => Conversation::STATUS_OPEN,
                // Ventana abierta: pasadas 24 h la bandeja bloquea el texto
                // libre, y el revisor vería el caso excepcional en vez del normal.
                'last_inbound_at' => now()->subMinutes(30),
                'last_message_at' => now()->subMinutes(5),
                'unread_count'    => 0,
            ],
        );

        $mensajes = [
            [
                'wamid.DEMO_CONV_IN_1',
                'Hi! Do you deliver on Saturdays?',
                ConversationMessage::DIRECTION_IN,
                ConversationMessage::AUTHOR_CONTACT,
                ConversationMessage::DELIVERY_DELIVERED,
                null,
            ],
            [
                'wamid.DEMO_CONV_OUT_1',
                'Hello! Yes, we deliver Saturdays from 9am to 2pm.',
                ConversationMessage::DIRECTION_OUT,
                ConversationMessage::AUTHOR_STAFF,
                ConversationMessage::DELIVERY_READ,
                $revisor->id,
            ],
        ];

        foreach ($mensajes as [$waId, $texto, $direccion, $autor, $entrega, $userId]) {
            ConversationMessage::firstOrCreate(
                ['wa_message_id' => $waId],
                [
                    'conversation_id' => $conversacion->id,
                    'user_id'         => $userId,
                    'author_type'     => $autor,
                    'direction'       => $direccion,
                    'body'            => $texto,
                    'delivery_status' => $entrega,
                ]
            );
        }

        // Sin al menos una plantilla, la pantalla con la que se evalúa
        // whatsapp_business_management se ve vacía.
        WhatsAppTemplate::updateOrCreate(
            [
                'whatsapp_account_id' => $cuenta->id,
                'name'                => 'delivery_confirmation',
                'language'            => 'en_US',
            ],
            [
                'meta_id'    => 'DEMO-TEMPLATE-0001',
                'category'   => 'UTILITY',
                'status'     => WhatsAppTemplate::STATUS_APPROVED,
                'components' => [[
                    'type' => 'BODY',
                    'text' => 'Hi {{1}}, your order {{2}} is out for delivery.',
                ]],
                'body_variables' => 2,
            ],
        );
    }
}
