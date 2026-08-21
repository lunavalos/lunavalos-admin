<?php

namespace Tests\Feature;

use App\Jobs\NotifyApiConsumers;
use App\Models\ApiConsumer;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La API de plataforma: lo que usan klwebapp, las landings y n8n.
 *
 * Lo que se cuida aquí, por orden de gravedad:
 *
 *  1. **El acotado.** Un consumidor atado a un cliente no puede tocar otro, ni
 *     mandándole el `client_id` ajeno en el cuerpo. Es una API que reparte
 *     acceso a mensajes de clientes finales de terceros.
 *  2. **La ventana de 24 h.** Un sistema externo no puede saltársela por
 *     llamar desde fuera; la regla es de Meta y vale igual para todos.
 *  3. **Que lo que se manda sea lo que se firma.** El defecto del `DELETE` de
 *     plantillas —el test miraba que se llamara, no QUÉ se mandaba— salió caro
 *     una vez. Aquí se miran las URL y los cuerpos.
 */
class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'app-secret-de-pruebas';

    private function cuenta(): WhatsAppAccount
    {
        return WhatsAppAccount::firstOrCreate(
            ['waba_id' => '2436841820155807'],
            ['name' => 'LunAvalos', 'access_token' => 'token-de-la-waba'],
        );
    }

    private function numero(?Client $client = null): WhatsAppNumber
    {
        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $this->cuenta()->id,
            'client_id'            => $client?->id,
            'phone_number_id'      => (string) fake()->unique()->numerify('##############'),
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    private function cliente(string $nombre): Client
    {
        return Client::create(['business_name' => $nombre]);
    }

    /** Devuelve [consumidor, token en claro]. */
    private function consumidor(?Client $client = null, array $permisos = ApiConsumer::ABILITIES): array
    {
        $consumidor = ApiConsumer::create([
            'name'      => 'klwebapp',
            'slug'      => 'klwebapp-' . fake()->unique()->numerify('####'),
            'client_id' => $client?->id,
            'status'    => ApiConsumer::STATUS_ACTIVE,
        ]);

        return [$consumidor, $consumidor->createToken('pruebas', $permisos)->plainTextToken];
    }

    private function conversacionAbierta(WhatsAppNumber $numero, ?Client $client, string $waId = '5218443410326'): Conversation
    {
        return Conversation::create([
            'client_id'          => $client?->id,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => $waId,
            'last_inbound_at'    => now()->subHour(),
            'last_message_at'    => now()->subHour(),
        ]);
    }

    // ---------------------------------------------------------------- acceso

    public function test_sin_token_no_se_entra(): void
    {
        $this->postJson('/api/v1/mensajes', ['to' => '5218443410326', 'body' => 'hola'])
            ->assertUnauthorized();
    }

    public function test_un_token_de_consumidor_desactivado_no_entra(): void
    {
        [$consumidor, $token] = $this->consumidor($this->cliente('Macadam'));
        $consumidor->update(['status' => ApiConsumer::STATUS_DISABLED]);

        $this->withToken($token)->getJson('/api/v1/yo')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'consumidor_inactivo');
    }

    public function test_un_token_sin_la_habilidad_de_enviar_no_envia(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);
        [, $token] = $this->consumidor($client, [ApiConsumer::ABILITY_LEER]);

        $this->withToken($token)
            ->postJson('/api/v1/mensajes', ['to' => '5218443410326', 'body' => 'hola'])
            ->assertForbidden();
    }

    public function test_yo_describe_el_alcance(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);
        [, $token] = $this->consumidor($client);

        $this->withToken($token)->getJson('/api/v1/yo')
            ->assertOk()
            ->assertJsonPath('alcance.atado', true)
            ->assertJsonPath('alcance.client_id', $client->id)
            ->assertJsonPath('alcance.cliente', 'Macadam')
            ->assertJsonCount(1, 'numeros');
    }

    // -------------------------------------------------------------- acotado

    public function test_un_consumidor_atado_no_puede_operar_sobre_otro_cliente(): void
    {
        $macadam = $this->cliente('Macadam');
        $otro    = $this->cliente('Otro cliente');

        $this->numero($macadam);
        $numeroAjeno = $this->numero($otro);

        [, $token] = $this->consumidor($macadam);

        Http::fake();

        // Manda el client_id Y el phone_number_id del otro cliente: los dos se
        // tienen que ignorar en favor del alcance del token.
        $this->withToken($token)->postJson('/api/v1/mensajes', [
            'to'              => '5218443410326',
            'body'            => 'hola',
            'client_id'       => $otro->id,
            'phone_number_id' => $numeroAjeno->phone_number_id,
        ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'numero_no_encontrado');

        Http::assertNothingSent();
    }

    public function test_un_consumidor_interno_tiene_que_nombrar_el_cliente(): void
    {
        [, $token] = $this->consumidor(null);

        $this->withToken($token)
            ->postJson('/api/v1/mensajes', ['to' => '5218443410326', 'body' => 'hola'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cliente_requerido');
    }

    public function test_una_conversacion_de_otro_cliente_da_404(): void
    {
        $macadam = $this->cliente('Macadam');
        $otro    = $this->cliente('Otro');

        $ajena = $this->conversacionAbierta($this->numero($otro), $otro);

        [, $token] = $this->consumidor($macadam);

        $this->withToken($token)->getJson("/api/v1/conversaciones/{$ajena->id}")
            ->assertNotFound();
    }

    // --------------------------------------------------------------- envíos

    public function test_enviar_texto_llega_a_graph_y_aterriza_en_la_conversacion(): void
    {
        $client = $this->cliente('Macadam');
        $numero = $this->numero($client);
        $this->conversacionAbierta($numero, $client);

        [, $token] = $this->consumidor($client);

        Http::fake([
            '*/messages' => Http::response(['messages' => [['id' => 'wamid.API1']]]),
        ]);

        $respuesta = $this->withToken($token)->postJson('/api/v1/mensajes', [
            // Con formato humano a propósito: una landing manda lo que escribió
            // la persona, no un wa_id normalizado.
            'to'   => '+52 1 844 341 0326',
            'body' => 'Gracias por tu interés',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('wa_message_id', 'wamid.API1')
            ->assertJsonPath('delivery_status', ConversationMessage::DELIVERY_SENT);

        // Aterrizó en la conversación que YA existía, no en una nueva: es lo
        // que evita dos historiales del mismo contacto.
        $this->assertSame(1, Conversation::count());

        $mensaje = ConversationMessage::firstWhere('wa_message_id', 'wamid.API1');
        $this->assertSame('Gracias por tu interés', $mensaje->body);
        $this->assertSame(ConversationMessage::DIRECTION_OUT, $mensaje->direction);

        Http::assertSent(function ($request) use ($numero) {
            return str_contains($request->url(), "/{$numero->phone_number_id}/messages")
                && $request['to'] === '5218443410326'
                && $request['text']['body'] === 'Gracias por tu interés';
        });
    }

    public function test_texto_libre_con_la_ventana_cerrada_se_rechaza_sin_llamar_a_meta(): void
    {
        $client = $this->cliente('Macadam');
        $numero = $this->numero($client);

        Conversation::create([
            'client_id'          => $client->id,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5218443410326',
            'last_inbound_at'    => now()->subDays(3),
        ]);

        [, $token] = $this->consumidor($client);

        Http::fake();

        $this->withToken($token)->postJson('/api/v1/mensajes', [
            'to'   => '5218443410326',
            'body' => 'hola',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ventana_cerrada');

        // Lo importante no es el 422 sino esto: no se gastó una llamada a Meta
        // ni se guardó un mensaje que el contacto nunca recibiría.
        Http::assertNothingSent();
        $this->assertSame(0, ConversationMessage::count());
    }

    public function test_un_contacto_nuevo_no_puede_recibir_texto_libre(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);
        [, $token] = $this->consumidor($client);

        Http::fake();

        // Sin entrante previo no hay ventana: iniciar conversación con texto
        // libre es justo lo que Meta no permite, y la API tiene que decirlo
        // en vez de dejar que falle en Graph.
        $this->withToken($token)->postJson('/api/v1/mensajes', [
            'to'   => '5218443410326',
            'body' => 'Hola, vimos tu solicitud',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ventana_cerrada');

        Http::assertNothingSent();
    }

    public function test_una_plantilla_si_inicia_la_conversacion(): void
    {
        $client = $this->cliente('Macadam');
        $numero = $this->numero($client);

        $plantilla = WhatsAppTemplate::create([
            'whatsapp_account_id' => $this->cuenta()->id,
            'meta_id'             => '123',
            'name'                => 'lead_recibido',
            'language'            => 'es_MX',
            'category'            => 'UTILITY',
            'status'              => WhatsAppTemplate::STATUS_APPROVED,
            'components'          => [['type' => 'BODY', 'text' => 'Hola {{1}}, recibimos tu solicitud.']],
            'body_variables'      => 1,
        ]);

        [, $token] = $this->consumidor($client);

        Http::fake([
            '*/messages' => Http::response(['messages' => [['id' => 'wamid.TPL1']]]),
        ]);

        $this->withToken($token)->postJson('/api/v1/mensajes/plantilla', [
            'to'          => '5218443410326',
            'template_id' => $plantilla->id,
            'parametros'  => ['Ana'],
        ])
            ->assertCreated()
            ->assertJsonPath('delivery_status', ConversationMessage::DELIVERY_SENT)
            // El cuerpo guardado es el texto ya sustituido: en el hilo tiene
            // que leerse lo que recibió el contacto.
            ->assertJsonPath('body', 'Hola Ana, recibimos tu solicitud.');

        $this->assertSame(1, Conversation::count());

        Http::assertSent(function ($request) {
            return $request['type'] === 'template'
                && $request['template']['name'] === 'lead_recibido';
        });
    }

    public function test_una_plantilla_de_otra_waba_no_se_puede_enviar(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);

        $otraCuenta = WhatsAppAccount::create([
            'waba_id'      => '999999999',
            'name'         => 'WABA ajena',
            'access_token' => 'x',
        ]);

        $ajena = WhatsAppTemplate::create([
            'whatsapp_account_id' => $otraCuenta->id,
            'meta_id'             => '456',
            'name'                => 'plantilla_ajena',
            'language'            => 'es_MX',
            'category'            => 'UTILITY',
            'status'              => WhatsAppTemplate::STATUS_APPROVED,
            'components'          => [['type' => 'BODY', 'text' => 'Hola']],
            'body_variables'      => 0,
        ]);

        [, $token] = $this->consumidor($client);

        Http::fake();

        $this->withToken($token)->postJson('/api/v1/mensajes/plantilla', [
            'to'          => '5218443410326',
            'template_id' => $ajena->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'plantilla_no_disponible');

        Http::assertNothingSent();
    }

    public function test_una_plantilla_sin_aprobar_no_se_envia(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);

        $pendiente = WhatsAppTemplate::create([
            'whatsapp_account_id' => $this->cuenta()->id,
            'meta_id'             => '789',
            'name'                => 'en_revision',
            'language'            => 'es_MX',
            'category'            => 'UTILITY',
            'status'              => WhatsAppTemplate::STATUS_PENDING,
            'components'          => [['type' => 'BODY', 'text' => 'Hola']],
            'body_variables'      => 0,
        ]);

        [, $token] = $this->consumidor($client);

        Http::fake();

        $this->withToken($token)->postJson('/api/v1/mensajes/plantilla', [
            'to'          => '5218443410326',
            'template_id' => $pendiente->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'plantilla_no_disponible');

        Http::assertNothingSent();
    }

    public function test_con_varios_numeros_activos_hay_que_elegir(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);
        $this->numero($client);

        [, $token] = $this->consumidor($client);

        Http::fake();

        // Adivinar el número mandaría el mensaje desde la identidad equivocada,
        // así que se falla en vez de elegir.
        $this->withToken($token)->postJson('/api/v1/mensajes', [
            'to'   => '5218443410326',
            'body' => 'hola',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'numero_ambiguo');

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- lectura

    public function test_la_lista_solo_trae_lo_del_cliente_del_token(): void
    {
        $macadam = $this->cliente('Macadam');
        $otro    = $this->cliente('Otro');

        $this->conversacionAbierta($this->numero($macadam), $macadam, '5210000000001');
        $this->conversacionAbierta($this->numero($otro), $otro, '5210000000002');

        [, $token] = $this->consumidor($macadam);

        $this->withToken($token)->getJson('/api/v1/conversaciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact_wa_id', '5210000000001')
            ->assertJsonPath('data.0.ventana_abierta', true);
    }

    public function test_las_plantillas_listadas_son_solo_las_aprobadas(): void
    {
        $client = $this->cliente('Macadam');
        $this->numero($client);

        foreach ([WhatsAppTemplate::STATUS_APPROVED, WhatsAppTemplate::STATUS_PENDING] as $i => $estado) {
            WhatsAppTemplate::create([
                'whatsapp_account_id' => $this->cuenta()->id,
                'meta_id'             => "meta-{$i}",
                'name'                => "plantilla_{$i}",
                'language'            => 'es_MX',
                'category'            => 'UTILITY',
                'status'              => $estado,
                'components'          => [['type' => 'BODY', 'text' => 'Hola {{1}}']],
                'body_variables'      => 1,
            ]);
        }

        [, $token] = $this->consumidor($client);

        $this->withToken($token)->getJson('/api/v1/plantillas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', WhatsAppTemplate::STATUS_APPROVED)
            ->assertJsonPath('data.0.body_variables', 1);

        // Con `todas` salen las dos: es lo que hace falta para diagnosticar
        // "¿por qué no aparece la que acabo de crear?".
        $this->withToken($token)->getJson('/api/v1/plantillas?todas=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------- webhooks

    public function test_un_entrante_despacha_la_notificacion_a_los_consumidores(): void
    {
        Bus::fake();

        $client = $this->cliente('Macadam');
        $numero = $this->numero($client);

        $this->enviarWebhook($this->eventoEntrante($numero))->assertOk();

        Bus::assertDispatched(NotifyApiConsumers::class);
    }

    public function test_el_webhook_saliente_va_firmado_y_solo_a_quien_corresponde(): void
    {
        $macadam = $this->cliente('Macadam');
        $otro    = $this->cliente('Otro');

        $suyo = ApiConsumer::create([
            'name' => 'landing-macadam', 'slug' => 'landing-macadam',
            'client_id' => $macadam->id, 'status' => ApiConsumer::STATUS_ACTIVE,
            'webhook_url' => 'https://macadam.test/hook', 'webhook_secret' => 'secreto-macadam',
        ]);

        // Mismo montaje, otro cliente: no debe recibir nada.
        ApiConsumer::create([
            'name' => 'landing-ajena', 'slug' => 'landing-ajena',
            'client_id' => $otro->id, 'status' => ApiConsumer::STATUS_ACTIVE,
            'webhook_url' => 'https://ajena.test/hook', 'webhook_secret' => 'secreto-ajeno',
        ]);

        $numero       = $this->numero($macadam);
        $conversacion = $this->conversacionAbierta($numero, $macadam);
        $mensaje      = $conversacion->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_CONTACT,
            'direction'       => ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => 'wamid.IN1',
            'type'            => 'text',
            'body'            => 'Quiero información',
            'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
        ]);

        Http::fake();

        (new NotifyApiConsumers($conversacion, $mensaje))->handle();

        Http::assertSentCount(1);

        Http::assertSent(function ($request) use ($suyo) {
            if ($request->url() !== 'https://macadam.test/hook') {
                return false;
            }

            // La firma tiene que valer sobre el cuerpo CRUDO tal cual salió.
            // Firmar el array y mandar otra serialización es exactamente cómo
            // se rompen estas firmas.
            $esperada = 'sha256=' . hash_hmac('sha256', $request->body(), $suyo->webhook_secret);

            return hash_equals($esperada, $request->header('X-LunAvalos-Signature')[0]);
        });
    }

    public function test_un_consumidor_sin_secreto_no_recibe_entregas(): void
    {
        $client = $this->cliente('Macadam');

        ApiConsumer::create([
            'name' => 'sin-secreto', 'slug' => 'sin-secreto',
            'client_id' => $client->id, 'status' => ApiConsumer::STATUS_ACTIVE,
            'webhook_url' => 'https://sin-secreto.test/hook',
        ]);

        $numero       = $this->numero($client);
        $conversacion = $this->conversacionAbierta($numero, $client);
        $mensaje      = $conversacion->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_CONTACT,
            'direction'       => ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => 'wamid.IN2',
            'type'            => 'text',
            'body'            => 'Hola',
            'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
        ]);

        Http::fake();

        (new NotifyApiConsumers($conversacion, $mensaje))->handle();

        // Sin secreto la entrega no se podría verificar del otro lado: mejor
        // no mandarla que mandarla sin firmar.
        Http::assertNothingSent();
    }

    // --------------------------------------------------------------- cifrado

    public function test_el_secreto_del_webhook_va_cifrado_en_reposo(): void
    {
        $consumidor = ApiConsumer::create([
            'name' => 'x', 'slug' => 'x-cifrado',
            'status' => ApiConsumer::STATUS_ACTIVE,
            'webhook_url' => 'https://x.test/hook',
            'webhook_secret' => 'secreto-en-claro',
        ]);

        $crudo = \DB::table('api_consumers')->where('id', $consumidor->id)->value('webhook_secret');

        $this->assertNotSame('secreto-en-claro', $crudo);
        $this->assertSame('secreto-en-claro', $consumidor->fresh()->webhook_secret);
    }

    // --------------------------------------------------------------- ayudas

    private function eventoEntrante(WhatsAppNumber $numero): array
    {
        return [
            'entry' => [[
                'id'      => '2436841820155807',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $numero->phone_number_id],
                        'contacts' => [['wa_id' => '5218443410326', 'profile' => ['name' => 'Ana']]],
                        'messages' => [[
                            'id'   => 'wamid.WEBHOOK1',
                            'from' => '5218443410326',
                            'type' => 'text',
                            'text' => ['body' => 'Hola'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * Meta firma el cuerpo CRUDO, así que hay que mandar el mismo texto que se
     * firmó: `postJson` reserializa y la firma deja de corresponder.
     */
    private function enviarWebhook(array $carga)
    {
        config(['services.whatsapp.app_secret' => self::APP_SECRET]);

        $cuerpo = json_encode($carga);

        return $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_ACCEPT'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $cuerpo, self::APP_SECRET),
        ], $cuerpo);
    }
}
