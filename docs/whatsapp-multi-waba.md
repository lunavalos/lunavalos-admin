# Plan de migración: WhatsApp multi-WABA (Tech Provider)

> Escrito el 2026-08-13 tras auditar el código, la instancia de n8n y el panel de Meta.
> **Revisado el 2026-08-16**: Fases 1 y 4 hechas, Tech Provider aprobado, y se
> incorpora la arquitectura del módulo de Conversaciones (§3.2).
> Sustituye al modelo descrito en `docs/n8n/README.md`, que quedó obsoleto.

## 1. Qué cambia y por qué

La decisión de producto es que **cada cliente sea dueño de su propia WABA** y nos
conceda acceso, en vez de dar de alta sus números bajo la WABA de LunAvalos.

Eso nos mueve del modelo "un negocio, un número" al modelo **Tech Provider**, y
tiene una consecuencia que invalida el diseño actual:

> El diseño de hoy asume **un token de Meta, estático, guardado en n8n**.
> Con multi-WABA hay **un token por cliente**, que llega dinámicamente en el
> onboarding y que el cliente puede revocar cuando quiera.

Una credencial Header Auth fija en n8n no puede sostener eso.

## 2. Estado real al 2026-08-13 (auditoría)

Vale la pena dejarlo escrito, porque el repo documenta una arquitectura que
**nunca se desplegó**.

### Código (Laravel) — correcto pero para el modelo equivocado

| Pieza | Estado |
|---|---|
| `app/Services/WhatsApp/WhatsAppService.php` | Funciona. Un único webhook global, sin noción de número. |
| `app/Http/Controllers/WhatsAppWebhookController.php` | Idempotente por `wa_message_id`. **Ignora `metadata.phone_number_id`.** |
| `app/Http/Middleware/VerifyN8nSecret.php` | Correcto (`hash_equals`, cierra si falta config). Pero espera a n8n, no a Meta. |
| Excepción CSRF (`bootstrap/app.php`) | Correcta. |
| Migración `2026_06_07_000000` | `whatsapp_wa_id`, `direction`, `wa_message_id` (unique). |
| `tests/Feature/WhatsAppWebhookSecurityTest.php` | Cubre el rechazo por secreto. |

### n8n — no participa

- Un solo workflow: `Actualizar Precios Gas`. **Ninguno** de los dos de `docs/n8n/`
  está importado. Los paths `/webhook/meta-whatsapp` y
  `/webhook/lunavalos-admin-whatsapp` devuelven `404 not registered`.
- Existe una credencial `WhatsApp account` de tipo **WhatsApp API** (nodo nativo),
  incompatible con los workflows del repo, que piden **Header Auth**.
- Variables (`$vars`) es función de pago y está bloqueada. No importa: los
  workflows usan `$env`, que es lo correcto para esta edición.

### Meta — app `LunAvalos Social` (`1531774538464754`)

| Punto | Estado |
|---|---|
| Business verification (`LunAvalos Manager`, `2424498274460318`) | ✅ Verified |
| Production setup (webhooks, número, pago, envío) | ✅ 4/4 |
| Campo `messages` suscrito | ✅ (`calls` también) |
| App Publish Status | ❌ **Unpublished** |
| Access Verification (Tech Provider) | ✅ **Verified** (aprobado el 2026-08-16) |
| App Review | ❌ Sin enviar |
| `whatsapp_business_messaging` | ⚠️ Standard Access ("Ready for testing", 46 llamadas) |
| `whatsapp_business_management` | ⚠️ Standard Access ("Ready for testing", 60 llamadas) |

### El bug que hay que arreglar sí o sí — ✅ corregido en código (2026-08-14, ver Fase 1)

El **Callback URL configurado en Meta apunta directo a Laravel**, no a n8n:

```
https://admin.lunavalos.com/whatsapp/webhook
```

Y con esa configuración está roto en las dos direcciones (verificado con curl):

```
GET  /whatsapp/webhook?hub.challenge=12345   → 405 Method Not Allowed
POST /whatsapp/webhook (con X-Hub-Signature) → 401 Unauthorized
```

1. El handshake de Meta es **GET**; `routes/web.php` solo registra **POST**.
2. `VerifyN8nSecret` exige `X-N8n-Secret`; Meta manda `X-Hub-Signature-256`.

Es decir: aunque se publicara la app hoy, **no entraría ni un mensaje**.

## 3. Arquitectura destino

### 3.1 Flujo de datos

```
Onboarding:
  Cliente ──Embedded Signup (FB JS SDK)──► Laravel
  Laravel ──code→token, /subscribed_apps──► Graph API
  Laravel guarda WhatsAppAccount{waba_id, phone_number_id, token cifrado}

Entrada (un solo endpoint para TODOS los clientes):
  Meta ──X-Hub-Signature-256──► Laravel /whatsapp/webhook
  Laravel enruta por entry[].id (WABA ID) → WhatsAppAccount → Client

Salida:
  Laravel ──token del cliente──► Graph API /{phone_number_id}/messages

Agente de IA y automatizaciones (desacoplado, por cola):
  Laravel ──evento──► n8n ──respuesta──► Laravel ──► Graph API
```

### 3.2 El módulo: Conversaciones, no Tickets

**WhatsApp deja de vivir dentro de Tickets y pasa a ser su propio módulo.**

El motivo es conceptual, y los datos de producción ya lo evidencian: el ticket
101 se creó el 2026-06-07, sigue en estado `Nuevos` dos meses después, y sus
mensajes incluyen `"Hola"`, `"Ok"` y `"g"`. Eso no es un ticket — es un
historial de chat disfrazado de ticket.

| | Ticket | Conversación |
|---|---|---|
| Alcance | un asunto | un contacto |
| Ciclo de vida | se abre, se trabaja, **se cierra** | **no se cierra**: el contacto vuelve meses después |
| Métrica | trabajo pendiente | tiempo de respuesta |

Forzarlas en la misma tabla produce tickets eternos, tickets basura por cada
saludo, y un tablero que deja de medir trabajo real.

**La conversación es lo primario; el ticket se deriva de ella** cuando hay
trabajo que rastrear — el modelo de Intercom, Front y Zendesk:

```
Conversación  (contacto + número)
   ├── mensajes en tiempo real
   ├── estado de la ventana de 24 h
   ├── plantillas cuando la ventana está cerrada
   ├── agente de IA activable por cliente
   └── [Crear ticket] ──► Ticket enlazado (tickets.conversation_id)
```

Además, todo lo que trae multi-WABA **hoy no tiene dónde vivir**: cuentas y
números por cliente, plantillas y su estado de aprobación, quality rating por
número, configuración del agente de IA, estado de entrega por mensaje. Nada de
eso es un ticket. El módulo les da lugar natural.

Lo existente no se tira: el histórico de los tickets de canal `whatsapp` se
migra a su conversación, y el ticket queda enlazado si de verdad representaba
trabajo (ver §9, Fase 2).

### 3.3 Decisión sobre n8n

La justificación original de n8n era la custodia del token. Con multi-WABA
**Laravel tiene que custodiar los tokens de todos modos**, porque Laravel es
quien corre el onboarding. Mantener n8n en el camino transaccional agregaría
latencia y un modo de falla sin ganar nada en seguridad.

**n8n sale de la entrada y de la salida.** Ambas cosas ya se cumplieron:
la entrada en la Fase 1 y la salida en la Fase 4.

**n8n se queda para el agente de IA y las automatizaciones por cliente**, que
es lo que sí hace bien: iterar prompts y flujos sin desplegar código. Recibe
eventos de Laravel y devuelve respuestas; nunca toca tokens de Meta.

Roles descartados, para que no se reabran:

| Rol | Veredicto |
|---|---|
| Custodio del token | ❌ Los tokens son por cliente y viven en Laravel |
| Pasarela de entrada | ❌ Meta llama directo a Laravel |
| Pasarela de salida | ❌ Laravel llama directo a Graph |
| Un workflow por cliente | ❌ Todos los eventos llegan al mismo Callback URL |
| **Agente de IA** | ✅ Fase 6 |
| **Automatizaciones por cliente** | ✅ Fase 6 |
| Router hacia otros sistemas | 🟡 Solo si aparece un segundo sistema consumiendo el mismo callback |

> Los dos workflows de `docs/n8n/*.json` implementan la arquitectura vieja
> (n8n como pasarela). **Están obsoletos y no deben importarse.**

## 4. Esquema de base de datos

Precedente a seguir: `SocialAccount`, que ya es per-`client_id` con token y
expiración. Dos diferencias: aquí **sí** ciframos el token, y agregamos el
número como entidad de primera clase.

> **Corrección respecto al primer borrador.** `client_id` **no** cuelga de
> `whatsapp_accounts` sino de `whatsapp_numbers`. Una WABA propia puede alojar
> números de varios clientes (Standard Access), así que es el **número**, no la
> WABA, lo que determina a qué cliente pertenece una conversación. Con
> `client_id` en el número, los dos modelos —WABA compartida y WABA por
> cliente— conviven sin cambiar el esquema, y la decisión sobre Macadam deja
> de bloquear el desarrollo.
>
> `client_id` es **nullable**: null significa "número propio de LunAvalos".
> No se inventa un `Client` para representarnos a nosotros mismos.

```php
// create_whatsapp_accounts_table
Schema::create('whatsapp_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('name');                       // "LunAvalos", "Grupo Macadam"

    $table->string('waba_id')->unique();          // enruta el webhook entrante
    $table->string('business_id')->nullable();    // portfolio del cliente

    // Token del cliente obtenido vía Embedded Signup. Cifrado en reposo:
    // es credencial de un tercero, no nuestra.
    $table->text('access_token');
    $table->timestamp('token_expires_at')->nullable();

    $table->string('status')->default('pending'); // pending|active|revoked|error
    $table->timestamp('last_error_at')->nullable();
    $table->text('last_error')->nullable();

    $table->foreignId('connected_by')->nullable()->constrained('users');
    $table->timestamps();
});

// create_whatsapp_numbers_table — una WABA puede tener varios números
Schema::create('whatsapp_numbers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
    // Null = número propio de LunAvalos.
    $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();

    $table->string('phone_number_id')->unique();  // el que va en la URL de Graph
    $table->string('display_phone_number');
    $table->string('verified_name')->nullable();
    $table->string('quality_rating')->nullable(); // GREEN|YELLOW|RED
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

En el modelo, cifrado por cast:

```php
protected function casts(): array
{
    return [
        'access_token'     => 'encrypted',
        'token_expires_at' => 'datetime',
    ];
}
```

> Nota aparte: `SocialAccount::$access_token` hoy es `$hidden` pero **no** está
> cifrado. Vale la pena migrarlo al mismo cast en un cambio separado.

### Tablas del módulo de Conversaciones

```php
// create_conversations_table
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('whatsapp_number_id')->constrained();

    $table->string('contact_wa_id');            // teléfono del cliente final
    $table->string('contact_name')->nullable(); // nombre de perfil de WhatsApp

    // Estado de bandeja, no ciclo de vida de trabajo: una conversación se
    // archiva cuando ya no requiere atención, y se reabre sola si el
    // contacto vuelve a escribir.
    $table->string('status')->default('open');  // open|snoozed|archived
    $table->foreignId('assigned_id')->nullable()->constrained('users');

    // Ventana de 24 h: si last_inbound_at tiene más de 24 h, el texto libre
    // ya no se entrega y hay que usar plantilla.
    $table->timestamp('last_inbound_at')->nullable();
    $table->timestamp('last_message_at')->nullable();  // orden de la bandeja
    $table->unsignedInteger('unread_count')->default(0);

    $table->boolean('ai_enabled')->default(false);
    $table->timestamps();

    // Un contacto tiene UNA conversación por número. Es la clave de todo el
    // modelo: sin esto se recae en abrir un hilo por mensaje.
    $table->unique(['whatsapp_number_id', 'contact_wa_id']);
    $table->index(['client_id', 'last_message_at']);
});

// create_conversation_messages_table
Schema::create('conversation_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

    // Quién habló. user_id es null cuando escribe el contacto o la IA.
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('author_type');              // contact|staff|ai
    $table->string('direction');                // in|out

    $table->string('wa_message_id')->nullable()->unique();  // idempotencia
    $table->string('type')->default('text');    // text|image|document|audio…
    $table->text('body')->nullable();
    $table->string('media_path')->nullable();

    // Lo que hoy falta y hace que los fallos de envío sean invisibles.
    $table->string('delivery_status')->default('pending'); // pending|sent|delivered|read|failed
    $table->text('delivery_error')->nullable();

    $table->timestamps();
    $table->index(['conversation_id', 'id']);
});
```

Y en `tickets`, el enlace hacia la conversación que lo originó:

```php
$table->foreignId('conversation_id')->nullable()->constrained();
```

> Los campos `tickets.whatsapp_wa_id`, `ticket_messages.direction` y
> `ticket_messages.wa_message_id` quedan **deprecados** una vez migrado el
> histórico. No se borran en la misma release que introduce las tablas nuevas:
> primero se migra, se verifica, y se limpian después.

## 5. Onboarding: Embedded Signup

Meta **no permite** que el cliente nos pegue un token a mano. Tiene que pasar
por Embedded Signup. El flujo:

1. Página en el admin (`/clients/{client}/whatsapp/connect`) que carga el
   Facebook JS SDK y lanza `FB.login()` con el `config_id` del Embedded Signup.
2. El cliente entra con su cuenta de Facebook, elige o crea su WABA y su número,
   y nos concede acceso.
3. El SDK nos devuelve un `code` de corta vida.
4. Laravel lo canjea por el token del negocio:
   `GET /v2X.0/oauth/access_token?client_id=…&client_secret=…&code=…`
5. Con ese token, Laravel consulta la WABA y sus números y crea
   `WhatsAppAccount` + `WhatsAppNumber`.
6. **Paso que hoy no existe en ningún lado y sin el cual no llega nada:**
   suscribir nuestra app al webhook de esa WABA:
   `POST /v2X.0/{waba_id}/subscribed_apps`

Cada paso de 4 a 6 debe ser idempotente: el cliente va a repetir el flujo.

> ⚠️ Los nombres exactos de parámetros y la versión de Graph cambian entre
> versiones. Verificar contra la doc vigente de Embedded Signup al implementar;
> lo de aquí es la forma del flujo, no una firma de API literal.

## 6. Webhook de entrada (reescritura)

Un solo endpoint para todos los clientes. Reemplaza a `VerifyN8nSecret`.

```php
// routes/web.php
Route::get('whatsapp/webhook',  [WhatsAppWebhookController::class, 'verify']);
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
    ->middleware(VerifyMetaSignature::class);
```

**`verify()`** — handshake. Compara `hub.verify_token` contra
`config('services.whatsapp.verify_token')` y devuelve `hub.challenge` **tal
cual, como texto plano**. Si devuelves JSON, Meta rechaza la suscripción.

**`VerifyMetaSignature`** — HMAC-SHA256 del **cuerpo crudo** con el App Secret,
comparado contra `X-Hub-Signature-256` con `hash_equals`.

```php
$esperada = 'sha256=' . hash_hmac('sha256', $request->getContent(), config('services.whatsapp.app_secret'));
```

Sobre el cuerpo crudo, no sobre el JSON reserializado: cualquier diferencia de
orden o de escapes rompe la firma.

**`receive()`** — dos cambios de fondo: enrutar por WABA, y aterrizar en
conversaciones en vez de tickets.

```php
foreach ($request->input('entry', []) as $entry) {
    $account = WhatsAppAccount::where('waba_id', $entry['id'] ?? '')->first();
    if (!$account) { continue; }   // WABA que ya no administramos

    // El número por el que entró determina el cliente y la conversación.
    $numero = WhatsAppNumber::where('phone_number_id', $phoneNumberId)->first();

    $conversacion = Conversation::firstOrCreate(
        ['whatsapp_number_id' => $numero->id, 'contact_wa_id' => $waId],
        ['client_id' => $account->client_id, 'contact_name' => $nombrePerfil],
    );
    // ...guardar el mensaje, refrescar last_inbound_at y despachar el job de IA
}
```

Además hay que procesar `value['statuses']`, que hoy se ignora por completo:
es lo que alimenta `delivery_status` y cierra el agujero de los envíos
fallidos invisibles.

Se conserva lo que ya funciona: idempotencia por `wa_message_id` y respuesta
200 rápida. El emparejamiento de cliente por teléfono desaparece — con
multi-WABA el cliente se deduce del número por el que entró el mensaje, que es
determinista, en vez de adivinarse comparando sufijos.

**El procesamiento pasa a una cola.** Hoy `markAsRead()` y `sendText()` corren
síncronos dentro del request; con un agente de IA de por medio eso rompe el
200 rápido que Meta exige y provoca reintentos.

## 7. Salida — ✅ hecha el 2026-08-16

`WhatsAppService` ya habla directo con Graph. Firma actual:

```php
public function sendText(
    string $to,
    string $message,
    ?string $phoneNumberId = null,   // multi-WABA: número del cliente
    ?string $token = null,           // multi-WABA: token del cliente
): ?string
```

Los dos últimos parámetros son opcionales y hoy caen a
`services.whatsapp.phone_number_id` / `token`. Cuando exista `WhatsAppNumber`,
el llamador pasará `$numero->phone_number_id` y
`$numero->account->access_token` sin que cambie nada para el resto.

Decisiones tomadas:

- **Reintentos solo en errores de conexión y 5xx.** Un 4xx vuelve a fallar
  igual, y reintentar un envío que quizá sí llegó le duplica el mensaje al
  contacto.
- **El error 131047 se loguea como `fuera_ventana: true`**, para que el fallo
  más común de producción deje de ser invisible.
- Se mantiene el criterio de que **un fallo de envío nunca tumba la petición
  que lo originó**.

## 8. La ventana de 24 horas

Hoy `TicketController::addMessage()` manda texto libre siempre. Fuera de la
ventana de 24h desde el último mensaje del cliente, Meta responde error
**131047** y exige plantilla aprobada.

Como `WhatsAppService` traga el error y devuelve `null`, el resultado actual es:
el staff escribe, el mensaje se guarda en el ticket, y **el cliente nunca lo
recibe sin que nadie se entere**. Multi-tenant esto se agrava — son N quality
ratings que se pueden quemar en silencio.

Mínimo a implementar:

1. `tickets.last_inbound_at`, para saber si la ventana está abierta.
2. Si está cerrada, la UI ofrece plantilla en vez de texto libre.
3. `ticket_messages.delivery_status` (`pending|sent|delivered|read|failed`) y
   `delivery_error`, alimentados por el webhook de `statuses`.
4. **Mostrar el fallo en la UI del ticket.** Es lo que falta hoy.

Suscribirse al campo `message_template_status_update` para enterarse cuando
Meta aprueba o rechaza una plantilla.

## 9. Fases

Las fases 1 y 2 son independientes y van en paralelo: el trámite con Meta es lo
que tiene reloj, y no depende de una línea de código.

**Fase 0 — Trámites**
- ✅ Access Verification / Tech Provider — **aprobado el 2026-08-16**.
- ❌ **Publicar la app.** Es el bloqueo activo: mientras esté `Unpublished`,
  Meta solo entrega los webhooks de prueba del dashboard, no tráfico real.
  No requiere video ni App Review — es un switch.
- ❌ App Review con **Advanced Access** en los dos permisos de WhatsApp.
  Requiere screencast, y el screencast requiere Embedded Signup funcionando
  (Fase 3). No se puede adelantar.

> **Atajo para el primer cliente (Macadam).** Standard Access ya permite operar
> WABAs que el propio negocio posee. Dando de alta el número del cliente bajo
> la WABA de LunAvalos (`2436841820155807`, hasta 20 números) se puede salir a
> producción sin Embedded Signup ni App Review. Costo: la WABA es nuestra, así
> que el quality rating es compartido entre todos los números. Sirve para un
> piloto, no para 15 clientes.

**Fase 1 — Arreglar el webhook — ✅ hecha el 2026-08-14**
- Ruta GET + handshake (`WhatsAppWebhookController::verify`). Devuelve el
  challenge como texto plano. Ojo con el detalle que rompe la verificación: PHP
  convierte los puntos de la query, así que los parámetros llegan como
  `hub_challenge`, no `hub.challenge`.
- `VerifyMetaSignature` (HMAC del cuerpo crudo) en lugar de `VerifyN8nSecret`,
  que se eliminó por quedar sin uso.
- Config `services.whatsapp` + variables en `.env.example`.
- 10 tests en `WhatsAppWebhookSecurityTest`: handshake OK/KO/sin config, firma
  ausente/inválida/válida-de-otro-cuerpo/sin app secret, alta de ticket e
  idempotencia.
- **Falta desplegar y poner `WHATSAPP_APP_SECRET` y `WHATSAPP_VERIFY_TOKEN` en
  el `.env` de producción**, y volver a guardar el Callback URL en Meta para que
  dispare el handshake.

**Fase 2 — Módulo de Conversaciones + esquema multi-tenant**

Backend — ✅ hecho el 2026-08-16:
- Migraciones y modelos `WhatsAppAccount` / `WhatsAppNumber`, con `client_id`
  en el número (ver §4) y `access_token` cifrado por cast.
- Migraciones y modelos `Conversation` / `ConversationMessage`.
- `tickets.conversation_id` para el enlace conversación → ticket.
- Webhook reescrito: enruta por `entry[].id` y `metadata.phone_number_id`,
  crea/reutiliza la conversación, y descarta eventos de WABAs ajenas.
- Procesa `value['statuses']` → `delivery_status` y `delivery_error`.
- `MarkWhatsAppMessageRead` en cola: ninguna llamada a Graph dentro del request.
- `php artisan whatsapp:backfill` — idempotente, con `--dry-run`. Los salientes
  históricos se migran como `failed` con la razón real: nunca se enviaron.
- 13 tests del webhook (18 en total con los de salida).

UI — ✅ hecha el 2026-08-16:
- `ConversationController` + rutas bajo `/conversaciones`.
- Bandeja de dos paneles (`Conversations/Index.vue`): lista filtrable por
  estado y hilo con burbujas, estado de entrega por mensaje y aviso de ventana
  cerrada.
- Acotado por `client_id`: un usuario de portal solo ve lo suyo, y el canal de
  broadcasting **es privado** y filtra igual — a diferencia del de tickets, que
  es público. Aquí viajan mensajes de clientes finales de terceros.
- `ConversationMessageSent` para el tiempo real.
- Responder bloqueado fuera de la ventana de 24 h, **antes** de intentar el
  envío.
- Botón **Crear ticket**, que deja la conversación abierta.
- Permisos `Ver Conversaciones` y `Responder Conversaciones` en
  `DatabaseSeeder`, y entrada en el sidebar.
- 9 tests en `ConversationInboxTest`.

Pendiente menor:
- Retirar el envío por WhatsApp de `TicketController::addMessage()`, que queda
  como camino heredado. No se toca todavía para no romper el flujo que hoy usa
  el equipo.

**Fase 3 — Embedded Signup — ✅ hecha el 2026-08-17**
- `WhatsAppOnboardingService`: canje del code, lectura de la WABA y sus números,
  `POST /{waba_id}/subscribed_apps` y revocación.
- `WhatsAppConnectController` + `WhatsApp/Connect.vue` con el Facebook JS SDK.
  El App Secret nunca sale del servidor: el navegador solo entrega el `code`.
- Idempotente por `waba_id` y `phone_number_id`: reconectar no duplica números
  ni desliga las conversaciones que ya cuelgan de ellos.
- Token del cliente cifrado en reposo (hay un test que lo verifica leyendo la
  columna en crudo).
- Desconexión: desuscribe la app, borra el token y apaga los números. Si Meta
  falla, se deja de usar el token igual.
- 8 tests en `WhatsAppOnboardingTest`.

Pendiente de configuración (no de código):
- Crear el flujo de Embedded Signup en el panel de Meta y poner su
  `configuration_id` en `WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID`. La pantalla avisa
  si falta.
- Añadir `WHATSAPP_APP_ID` en producción.

**Fase 4 — Salida directa a Graph — ✅ hecha el 2026-08-16**
- `WhatsAppService` contra `graph.facebook.com`, con número y token opcionales
  como puerta a multi-WABA.
- Config `services.whatsapp.token` / `phone_number_id` / `business_account_id`.
- Eliminado el bloque `services.n8n` y las variables `N8N_*`, que ya no lee
  nadie.
- 5 tests nuevos: URL y Bearer correctos, `status: read`, número y token
  alternos, `null` sin lanzar ante error 131047, y no llamar a Meta sin config.

**Fase 5 — Ventana de 24h y plantillas**
- Procesar `value['statuses']` para alimentar `delivery_status`.
- Bloquear texto libre y ofrecer plantilla cuando `last_inbound_at` > 24 h.
- **Mostrar el fallo de entrega en la UI.** Es lo que hoy falta.

**Fase 6 — Agente de IA y automatizaciones (n8n)**
- Contrato de eventos Laravel → n8n, sin tokens de Meta de por medio.
- Workflow con nodo de agente: recibe la conversación, devuelve la respuesta.
- `conversations.ai_enabled` para activarlo por cliente.
- Transparencia sobre la automatización en el primer mensaje del bot.

## 10. Config

```env
# Entrada (ya en producción)
WHATSAPP_APP_ID=1531774538464754
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_GRAPH_VERSION=v26.0

# Salida (ya en producción)
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=1230737580126123
WHATSAPP_BUSINESS_ACCOUNT_ID=2436841820155807
WHATSAPP_TIMEOUT=10

# Fase 3
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=
```

> ⚠️ En producción la variable de versión está como `WHATSAPP_API_VERSION`, y
> el código lee `WHATSAPP_GRAPH_VERSION`. Hoy no rompe nada porque el default
> del código ya es `v26.0`, pero cambiarla en el panel no surte efecto.
> **Renombrarla.**

> Las variables `N8N_*` quedaron huérfanas en producción tras la Fase 4. Son
> inofensivas, pero conviene borrarlas para que nadie las crea vigentes.

## 11. Riesgos

- **Fecha límite del 10/12/2026.** Si Access Verification no se completa, Meta
  restringe la app. Es el riesgo con reloj.
- **Business Verification de cada cliente.** Su WABA no envía a escala sin
  verificar. Es la fricción real del onboarding, y no depende de nosotros.
- **Revocación silenciosa.** El cliente puede quitar el acceso desde su
  Business Manager. Hace falta detectar el token muerto y avisar, no fallar en
  silencio.
- **Rechazo de App Review.** Causas comunes: screencast poco claro del flujo,
  política de privacidad que no menciona el tratamiento de datos de WhatsApp,
  o instrucciones de prueba que el revisor no puede reproducir.

## 12. Pendientes de decisión

- **¿El número de Macadam va bajo la WABA de LunAvalos o Macadam tendrá la
  suya?** Determina si el piloto sale con Standard Access esta semana o espera
  a Advanced Access.
- ¿El número actual `+52 1 844 341 0326` se queda como el de LunAvalos, o migra?
- ¿Los clientes ven su conversación en el portal, o solo el staff?
  (El esquema lo soporta: `conversations.client_id` + permiso de Spatie.)
- ¿El agente de IA responde siempre, o solo fuera de horario / cuando nadie
  toma la conversación?
