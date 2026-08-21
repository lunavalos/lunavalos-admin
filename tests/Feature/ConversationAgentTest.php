<?php

namespace Tests\Feature;

use App\Jobs\ResponderConIA;
use App\Models\AiAgent;
use App\Models\AiUsage;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppNumber;
use App\Services\AI\ClaudeGateway;
use App\Services\AI\ClaudeRespuesta;
use App\Services\AI\ConversationAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El agente de IA de las conversaciones.
 *
 * Lo que se cuida aquí, por orden de gravedad:
 *
 *  1. **Que se calle cuando debe.** Un bot contestando encima de la persona
 *     que ya está atendiendo es la peor cara que puede dar un negocio. Se
 *     comprueba en los dos momentos: al despachar y dentro del job, porque
 *     entre uno y otro pasan segundos reales.
 *  2. **El tope de gasto.** Es lo que sostiene el modelo de cobro elegido —
 *     pagamos nosotros—. Un tope que no se aplica es una intención.
 *  3. **El aviso de automatización.** Va en el primer mensaje del agente, y
 *     solo en el primero.
 *
 * El gateway se sustituye por un doble: el SDK trae su propio cliente HTTP, así
 * que `Http::fake()` no lo alcanzaría y estos tests saldrían a red de verdad.
 */
class ConversationAgentTest extends TestCase
{
    use RefreshDatabase;

    /** Lo último que se le pidió al modelo, para poder mirarlo. */
    private ?array $ultimoHistorial = null;
    private ?string $ultimoPrompt = null;

    private function fingirClaude(ClaudeRespuesta $respuesta): void
    {
        $this->app->bind(ClaudeGateway::class, fn () => new class ($respuesta, $this) implements ClaudeGateway {
            public function __construct(
                private ClaudeRespuesta $respuesta,
                private ConversationAgentTest $test,
            ) {
            }

            public function responder(AiAgent $agente, string $promptDelSistema, array $mensajes): ClaudeRespuesta
            {
                $this->test->registrarLlamada($promptDelSistema, $mensajes);

                return $this->respuesta;
            }
        });
    }

    public function registrarLlamada(string $prompt, array $historial): void
    {
        $this->ultimoPrompt    = $prompt;
        $this->ultimoHistorial = $historial;
    }

    private function fingirClaudeQueFalla(): void
    {
        $this->app->bind(ClaudeGateway::class, fn () => new class implements ClaudeGateway {
            public function responder(AiAgent $agente, string $promptDelSistema, array $mensajes): ClaudeRespuesta
            {
                throw new \RuntimeException('la API está caída');
            }
        });
    }

    private function numero(?Client $client = null): WhatsAppNumber
    {
        $cuenta = WhatsAppAccount::firstOrCreate(
            ['waba_id' => '2436841820155807'],
            ['name' => 'LunAvalos', 'access_token' => 'token'],
        );

        return WhatsAppNumber::create([
            'whatsapp_account_id'  => $cuenta->id,
            'client_id'            => $client?->id,
            'phone_number_id'      => (string) fake()->unique()->numerify('##############'),
            'display_phone_number' => '+52 1 844 341 0326',
        ]);
    }

    /** @return array{Conversation, AiAgent, Client} */
    private function montaje(array $agente = [], array $conversacion = []): array
    {
        $client = Client::create(['business_name' => 'Grupo Macadam']);

        $ai = AiAgent::create(array_merge([
            'client_id' => $client->id,
            'name'      => 'Asistente Macadam',
            'enabled'   => true,
            'model'     => 'claude-opus-5',
        ], $agente));

        config(['services.anthropic.api_key' => 'sk-ant-de-pruebas']);

        $conv = Conversation::create(array_merge([
            'client_id'          => $client->id,
            'whatsapp_number_id' => $this->numero($client)->id,
            'contact_wa_id'      => '5218443410326',
            'ai_enabled'         => true,
            'last_inbound_at'    => now()->subMinutes(2),
            'last_message_at'    => now()->subMinutes(2),
        ], $conversacion));

        $conv->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_CONTACT,
            'direction'       => ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => 'wamid.' . fake()->unique()->numerify('#####'),
            'type'            => 'text',
            'body'            => '¿A qué hora abren?',
            'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
        ]);

        return [$conv, $ai, $client];
    }

    // ------------------------------------------------- cuándo contesta

    public function test_contesta_y_el_mensaje_queda_marcado_como_de_la_ia(): void
    {
        [$conv] = $this->montaje();

        $this->fingirClaude(new ClaudeRespuesta(
            texto: 'Abrimos de 9 a 6, de lunes a viernes.',
            inputTokens: 1200,
            outputTokens: 40,
        ));

        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.IA1']]])]);

        $mensaje = app(ConversationAgent::class)->responder($conv);

        $this->assertNotNull($mensaje);
        $this->assertSame(ConversationMessage::AUTHOR_AI, $mensaje->author_type);
        $this->assertSame(ConversationMessage::DIRECTION_OUT, $mensaje->direction);
        $this->assertSame(ConversationMessage::DELIVERY_SENT, $mensaje->delivery_status);
        $this->assertStringContainsString('Abrimos de 9 a 6', $mensaje->body);

        // Salió de verdad hacia Meta, no solo se guardó.
        Http::assertSent(fn ($r) => $r['to'] === '5218443410326');
    }

    public function test_no_contesta_si_alguien_del_equipo_tomo_la_conversacion(): void
    {
        $usuario = User::factory()->create();

        [$conv] = $this->montaje(conversacion: ['assigned_id' => $usuario->id]);

        $this->assertFalse($conv->debeResponderIa());

        // Y el job tampoco, aunque le llegue: es la carrera real — la
        // conversación se toma DESPUÉS de encolar.
        Http::fake();
        $this->fingirClaude(new ClaudeRespuesta(texto: 'no debería salir'));

        (new ResponderConIA($conv))->handle(app(ConversationAgent::class));

        Http::assertNothingSent();
        $this->assertSame(0, ConversationMessage::where('author_type', ConversationMessage::AUTHOR_AI)->count());
    }

    public function test_no_contesta_con_el_toggle_apagado(): void
    {
        [$conv] = $this->montaje(conversacion: ['ai_enabled' => false]);

        $this->assertFalse($conv->debeResponderIa());
    }

    public function test_no_contesta_si_el_agente_esta_deshabilitado(): void
    {
        [$conv] = $this->montaje(agente: ['enabled' => false]);

        Http::fake();
        $this->fingirClaude(new ClaudeRespuesta(texto: 'no debería salir'));

        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();
    }

    public function test_no_contesta_si_no_hay_llave(): void
    {
        [$conv] = $this->montaje();
        config(['services.anthropic.api_key' => null]);

        Http::fake();
        $this->fingirClaude(new ClaudeRespuesta(texto: 'no debería salir'));

        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();
    }

    public function test_un_cliente_sin_agente_no_rompe_nada(): void
    {
        $client = Client::create(['business_name' => 'Sin agente']);

        $conv = Conversation::create([
            'client_id'          => $client->id,
            'whatsapp_number_id' => $this->numero($client)->id,
            'contact_wa_id'      => '5210000000009',
            'ai_enabled'         => true,
            'last_inbound_at'    => now()->subMinutes(2),
        ]);

        $this->assertNull(app(ConversationAgent::class)->responder($conv));
    }

    // ------------------------------------------------------- tope de gasto

    public function test_deja_de_contestar_al_llegar_al_tope(): void
    {
        [$conv, $ai] = $this->montaje(agente: ['monthly_token_limit' => 1000]);

        AiUsage::create([
            'ai_agent_id'   => $ai->id,
            'period'        => AiUsage::periodoActual(),
            'input_tokens'  => 900,
            'output_tokens' => 100,
        ]);

        Http::fake();
        $this->fingirClaude(new ClaudeRespuesta(texto: 'no debería salir'));

        $this->assertTrue($ai->superoElTope());
        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();
    }

    public function test_los_tokens_de_cache_no_cuentan_para_el_tope(): void
    {
        [, $ai] = $this->montaje(agente: ['monthly_token_limit' => 1000]);

        AiUsage::create([
            'ai_agent_id'       => $ai->id,
            'period'            => AiUsage::periodoActual(),
            'input_tokens'      => 100,
            'output_tokens'     => 50,
            // Cuestan ~10% de lo normal: si contaran, el tope castigaría justo
            // lo que abarata el agente.
            'cache_read_tokens' => 50_000,
        ]);

        $this->assertFalse($ai->superoElTope());
    }

    public function test_el_consumo_se_acumula_por_mes(): void
    {
        [$conv, $ai] = $this->montaje();

        $this->fingirClaude(new ClaudeRespuesta(
            texto: 'Claro que sí.',
            inputTokens: 1500,
            outputTokens: 60,
            cacheReadTokens: 1200,
        ));

        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

        app(ConversationAgent::class)->responder($conv);

        $consumo = AiUsage::where('ai_agent_id', $ai->id)
            ->where('period', AiUsage::periodoActual())
            ->first();

        $this->assertNotNull($consumo);
        $this->assertSame(1500, $consumo->input_tokens);
        $this->assertSame(60, $consumo->output_tokens);
        $this->assertSame(1200, $consumo->cache_read_tokens);
        $this->assertSame(1, $consumo->messages);
    }

    public function test_un_rechazo_del_modelo_se_cobra_igual_y_no_manda_nada(): void
    {
        [$conv, $ai] = $this->montaje();

        $this->fingirClaude(new ClaudeRespuesta(
            texto: null,
            inputTokens: 800,
            outputTokens: 0,
            declinado: true,
            categoriaDelRechazo: 'cyber',
        ));

        Http::fake();

        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();

        // Anthropic lo cobra igual: un tope que no cuenta lo gastado no es tope.
        $this->assertSame(800, AiUsage::where('ai_agent_id', $ai->id)->first()->input_tokens);
    }

    public function test_si_la_api_falla_la_conversacion_queda_para_el_equipo(): void
    {
        [$conv] = $this->montaje();

        $this->fingirClaudeQueFalla();
        Http::fake();

        // No relanza: el resultado correcto es "sin respuesta automática", no
        // un job en la cola de fallidos y una conversación rota.
        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------- el aviso

    public function test_el_primer_mensaje_lleva_el_aviso_de_automatizacion(): void
    {
        [$conv] = $this->montaje();

        $this->fingirClaude(new ClaudeRespuesta(texto: 'Abrimos de 9 a 6.'));
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.A']]])]);

        $primero = app(ConversationAgent::class)->responder($conv);

        $this->assertStringContainsString('asistente automático', $primero->body);
        $this->assertStringContainsString('Abrimos de 9 a 6.', $primero->body);
    }

    public function test_el_aviso_no_se_repite(): void
    {
        [$conv] = $this->montaje();

        $this->fingirClaude(new ClaudeRespuesta(texto: 'Primera.'));

        // Un wamid distinto por envío: la columna es única, y repetirlo haría
        // fallar el segundo por una razón que nada tiene que ver con el aviso.
        Http::fake(['*/messages' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.A1']]])
            ->push(['messages' => [['id' => 'wamid.A2']]]),
        ]);

        app(ConversationAgent::class)->responder($conv);

        // Llega otro entrante y vuelve a contestar.
        $conv->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_CONTACT,
            'direction'       => ConversationMessage::DIRECTION_IN,
            'wa_message_id'   => 'wamid.IN2',
            'type'            => 'text',
            'body'            => '¿Y los sábados?',
            'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
        ]);
        $conv->registrarEntrante();

        $this->fingirClaude(new ClaudeRespuesta(texto: 'Los sábados de 10 a 2.'));

        $segundo = app(ConversationAgent::class)->responder($conv->fresh());

        $this->assertStringNotContainsString('asistente automático', $segundo->body);
        $this->assertSame('Los sábados de 10 a 2.', $segundo->body);
    }

    // -------------------------------------------------- lo que ve el modelo

    public function test_el_historial_alterna_papeles_y_termina_en_el_contacto(): void
    {
        [$conv] = $this->montaje();

        $conv->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_STAFF,
            'direction'       => ConversationMessage::DIRECTION_OUT,
            'wa_message_id'   => 'wamid.OUT1',
            'type'            => 'text',
            'body'            => 'De 9 a 6.',
            'delivery_status' => ConversationMessage::DELIVERY_SENT,
        ]);

        // Dos entrantes seguidos: lo normal en WhatsApp.
        foreach (['¿Y los sábados?', '¿Tienen estacionamiento?'] as $i => $texto) {
            $conv->messages()->create([
                'author_type'     => ConversationMessage::AUTHOR_CONTACT,
                'direction'       => ConversationMessage::DIRECTION_IN,
                'wa_message_id'   => "wamid.IN{$i}x",
                'type'            => 'text',
                'body'            => $texto,
                'delivery_status' => ConversationMessage::DELIVERY_DELIVERED,
            ]);
        }

        $historial = app(ConversationAgent::class)->historial($conv);

        $this->assertSame(
            [
                ['role' => 'user',      'content' => '¿A qué hora abren?'],
                ['role' => 'assistant', 'content' => 'De 9 a 6.'],
                ['role' => 'user',      'content' => "¿Y los sábados?\n¿Tienen estacionamiento?"],
            ],
            $historial,
        );
    }

    public function test_no_llama_al_modelo_si_lo_ultimo_es_nuestro(): void
    {
        [$conv] = $this->montaje();

        $conv->messages()->create([
            'author_type'     => ConversationMessage::AUTHOR_STAFF,
            'direction'       => ConversationMessage::DIRECTION_OUT,
            'wa_message_id'   => 'wamid.OUT9',
            'type'            => 'text',
            'body'            => 'Ya te contesté.',
            'delivery_status' => ConversationMessage::DELIVERY_SENT,
        ]);

        Http::fake();
        $this->fingirClaude(new ClaudeRespuesta(texto: 'no debería salir'));

        // Nada nuevo que responder: llamar al modelo sería gastar por gastar.
        $this->assertSame([], app(ConversationAgent::class)->historial($conv));
        $this->assertNull(app(ConversationAgent::class)->responder($conv));
        Http::assertNothingSent();
    }

    public function test_el_prompt_se_arma_con_la_ficha_del_cliente(): void
    {
        [$conv, $ai, $client] = $this->montaje();

        $client->update([
            'briefing_context'         => 'Constructora de vivienda media en Saltillo.',
            'briefing_target_audience' => 'Familias jóvenes que compran su primera casa.',
        ]);

        $prompt = app(ConversationAgent::class)->promptDelSistema($ai->fresh());

        $this->assertStringContainsString('Grupo Macadam', $prompt);
        $this->assertStringContainsString('Constructora de vivienda media', $prompt);
        $this->assertStringContainsString('Familias jóvenes', $prompt);
        // Las barreras que evitan que el agente comprometa al negocio.
        $this->assertStringContainsString('No inventas precios', $prompt);
    }

    public function test_un_prompt_propio_gana_sobre_la_ficha(): void
    {
        [, $ai, $client] = $this->montaje(agente: ['system_prompt' => 'Eres un bot de pruebas.']);

        $client->update(['briefing_context' => 'Constructora.']);

        $prompt = app(ConversationAgent::class)->promptDelSistema($ai->fresh());

        $this->assertSame('Eres un bot de pruebas.', $prompt);
        $this->assertStringNotContainsString('Constructora', $prompt);
    }

    // ------------------------------------------------------------ el webhook

    public function test_un_entrante_despacha_el_job_cuando_toca(): void
    {
        Bus::fake();

        $client = Client::create(['business_name' => 'Macadam']);
        $numero = $this->numero($client);

        Conversation::create([
            'client_id'          => $client->id,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5218443410326',
            'ai_enabled'         => true,
        ]);

        $this->webhook($numero)->assertOk();

        Bus::assertDispatched(ResponderConIA::class);
    }

    public function test_un_entrante_no_despacha_el_job_con_la_ia_apagada(): void
    {
        Bus::fake();

        $client = Client::create(['business_name' => 'Macadam']);
        $numero = $this->numero($client);

        Conversation::create([
            'client_id'          => $client->id,
            'whatsapp_number_id' => $numero->id,
            'contact_wa_id'      => '5218443410326',
            'ai_enabled'         => false,
        ]);

        $this->webhook($numero)->assertOk();

        Bus::assertNotDispatched(ResponderConIA::class);
    }

    private function webhook(WhatsAppNumber $numero)
    {
        config(['services.whatsapp.app_secret' => 'secreto-de-pruebas']);

        $cuerpo = json_encode([
            'entry' => [[
                'id'      => '2436841820155807',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $numero->phone_number_id],
                        'contacts' => [['wa_id' => '5218443410326', 'profile' => ['name' => 'Ana']]],
                        'messages' => [[
                            'id'   => 'wamid.WH' . fake()->unique()->numerify('####'),
                            'from' => '5218443410326',
                            'type' => 'text',
                            'text' => ['body' => '¿A qué hora abren?'],
                        ]],
                    ],
                ]],
            ]],
        ]);

        return $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_ACCEPT'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $cuerpo, 'secreto-de-pruebas'),
        ], $cuerpo);
    }
}
