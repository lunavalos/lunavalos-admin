# Plan de migración: WhatsApp multi-WABA (Tech Provider)

> Escrito el 2026-08-13 tras auditar el código, la instancia de n8n y el panel de Meta.
> **Revisado el 2026-08-16**: Fases 1 y 4 hechas, Tech Provider aprobado, y se
> incorpora la arquitectura del módulo de Conversaciones (§3.2).
> **Revisado el 2026-08-17**: Fases 3 y 5 hechas. La configuración de Embedded
> Signup se creó en el panel y App Review resultó estar **sin enviar**, no en
> revisión.
> **Revisado el 2026-08-19**: app publicada, el toggle del JS SDK activado, y la
> cuenta de revisión actualizada para que pueda evaluar WhatsApp.
> **Revisado el 2026-08-20**: producción quedó apuntando al número real, el
> webhook de entrada se verificó de punta a punta con un mensaje real, y se
> grabaron los tres videos de App Review (§9, Fase 0). Además se corrigieron
> tres defectos que solo aparecieron al usar la API de verdad —el tiempo real
> de la bandeja y dos del flujo de plantillas— documentados abajo. Queda
> pendiente rellenar los formularios de App Review y renombrar
> `WHATSAPP_API_VERSION` (§10).
> **Revisado el 2026-08-21**: se auditó el §0 contra el código —C7 ya estaba
> hecho— y se implementó la **API de plataforma** (Fase 6), que no estaba en el
> plan original: sin ella ningún sistema externo podía usar este WhatsApp. El
> agente de IA pasa a Fase 7, y se decidió que corre en Laravel, no en n8n.
> Sustituye al modelo descrito en `docs/n8n/README.md`, que quedó obsoleto.

## 0. Qué falta (al 2026-08-20)

Todo lo de abajo está verificado contra producción o contra el panel de Meta.
Lo que no aparece aquí, está hecho.

### A. Bloquea App Review

| # | Qué | Quién |
|---|---|---|
| A1 | Recortar la lista de chats personales del video de mensajería y ponerle rótulos | tú |
| A2 | Rótulos al video de plantillas y al social | tú |
| A3 | Grabar un clip corto del **flujo de conexión** (Conectar otra red → login → selector de páginas). El video social empieza con la cuenta ya conectada, así que `pages_show_list` y `business_management` se quedan sin evidencia | tú |
| A4 | Correr `REVIEWER_PASSWORD=... php artisan db:seed --class=PlatformReviewerSeeder` en producción | tú |
| A5 | Entrar con `platform-reviewer@lunavalos.com` y recorrer Conversaciones, Plantillas WA y Social. Si falta una entrada del sidebar, el revisor topa con un callejón sin salida | tú |
| A6 | Rellenar **Allowed usage** (9 permisos: descripción + screencast + aceptar), **Data handling** y **Reviewer instructions**. Textos listos en `AJUSTES/app-review-answers.md` | tú |
| A7 | Enviar | tú |

> Los tres videos están grabados contra el número real. `public_profile` no
> pide screencast, solo la casilla de conformidad. Las *API test calls* de los
> 9 permisos ya salen en verde solas.

### B. Higiene de producción

Revisada contra el `.env` de producción el 2026-08-21. **B1–B5 cerradas.**

| # | Qué | Estado |
|---|---|---|
| B1 | Renombrar `WHATSAPP_API_VERSION` → `WHATSAPP_GRAPH_VERSION` | ✅ Hecho |
| B2 | Borrar las `N8N_*` | ✅ Hecho |
| B3 | Corregir `EVIEWER_EMAIL` → `REVIEWER_EMAIL` | ✅ Hecho |
| B4 | Rotar `WHATSAPP_APP_SECRET` | ✅ Hecho — se rotaron varios tokens |
| B5 | Ignorar `AJUSTES/` | ✅ Hecho el 2026-08-21. Antes solo estaba `/AJUSTES/*.mp4`, así que la captura con el App Secret legible **no** estaba protegida |
| **B6** | **`BROADCAST_CONNECTION=reverb` en producción** | ✅ Puesta el 2026-08-21. **Falta comprobarla en un navegador** (ver abajo) |

### B6 — el tiempo real no llega a producción

`config/broadcasting.php:18` cae a `'null'` cuando la variable no está, y con
ese driver `broadcast()` **no hace nada**. Reverb corre
(`docker/supervisord.conf:35`) pero nunca recibe qué retransmitir.

Todo lo del 2026-08-20 —el `ConversationMessageSent` del webhook, el
`ConversationUpdated` de la lista, los dos canales de bandeja— queda inerte: un
entrante sigue exigiendo recargar.

**Por qué pasó desapercibido.** Es el mismo falso verde que este documento ya
describe para los tests, aplicado dos veces más:

| Lo que parecía probarlo | Lo que prueba de verdad |
|---|---|
| `POST /broadcasting/auth 200` en producción | Que el canal **autorizó**. Es una ruta HTTP del middleware `web` y responde 200 con cualquier driver |
| `ConversationInboxRealtimeTest` en verde | `Event::fake()` + `assertDispatched`: que **se llama** a broadcast, no que se **entregue** |

Y `.env.example` traía `BROADCAST_CONNECTION=log`, que en producción tampoco
entrega nada —escribe al log— así que copiarlo no salvaba. Se le añadió el
aviso.

> **Cómo verificarlo de verdad:** abrir Conversaciones en dos navegadores,
> mandar un WhatsApp real, y comprobar que aparece en los dos **sin recargar**.
> Ninguna otra señal sirve.

### C. Código pendiente, después de App Review

| # | Qué | Por qué importa |
|---|---|---|
| C1 | **Fase 7** — agente de IA por cliente | `conversations.ai_enabled` existe como columna y como botón, pero **no lo lee nadie**: hoy lo prendes y no pasa nada |
| C2 | Avisar antes de que muera un token de cliente | `token_expires_at` ya se guarda; nada avisa |
| C3 | Detectar revocación silenciosa | El cliente puede quitarnos el acceso desde su Business Manager sin avisar |
| C4 | Probar `conversation.{id}` | Sigue siendo closure, y con `BROADCAST_CONNECTION=null` un test contra `/broadcasting/auth` da falso verde |
| C5 | Retirar el envío por WhatsApp de `TicketController::addMessage()` | Camino heredado que sigue vivo. Ahora que existe `ConversationSender`, el reemplazo es directo |
| C6 | Limpiar columnas deprecadas | `tickets.whatsapp_wa_id`, `ticket_messages.direction` y `wa_message_id`. Bloqueado por C5 |
| C7 | ~~Cifrar `SocialAccount::$access_token`~~ | ✅ **Ya estaba hecho.** `access_token` y `refresh_token` tienen cast `encrypted`, con `SocialAccountTokenEncryptionTest` verificándolo. La entrada estaba desactualizada |
| C8 | `FACEBOOK_GRAPH_VERSION=v19.0` | Confirmado en `.env`. El default del código es `v21.0`, pero la variable lo pisa. Afecta a redes sociales, no a WhatsApp |

### D. Lo que sigue sin probarse contra Meta

**Embedded Signup nunca se ha ejecutado de verdad.** Los 8 tests de la Fase 3
usan HTTP falseado, y esta semana demostró tres veces lo que eso vale: el
`DELETE` de plantillas, los ejemplos de `example.body_text` y el enrutado del
webhook estaban todos "probados" y todos rotos.

No se puede adelantar: requiere un negocio ajeno con su propia cuenta de
Facebook, y con Standard Access Meta ni siquiera le pediría los permisos. La
primera conexión real —Macadam, tras Advanced Access— es la prueba. Conviene
tener los logs a mano ese día.

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
| App Publish Status | ✅ **Published** (el 2026-08-19) |
| Access Verification (Tech Provider) | ✅ **Verified** (aprobado el 2026-08-16) |
| App Review | ❌ **Not submitted** (verificado en el panel el 2026-08-17) |
| Configuración de Embedded Signup | ✅ Creada el 2026-08-17 → `1006528675722697` (token sin caducidad) |
| Campo `message_template_status_update` | ✅ Suscrito |
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
| **Agente de IA** | ❌ Descartado el 2026-08-21 — corre en Laravel (Fase 7) |
| **Conectores de ads y terceros** | ✅ Fase 7, fuera del camino crítico |
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
- ✅ **Publicar la app** — hecho el 2026-08-19. Mientras estuvo `Unpublished`,
  Meta solo entregaba los webhooks de prueba del dashboard, no tráfico real.
  No requería video ni App Review — era un switch.
- 🟡 App Review con **Advanced Access** en los dos permisos de WhatsApp.
  Al 2026-08-17 la solicitud está en **Not submitted**: los 9 permisos
  (`whatsapp_business_messaging`, `whatsapp_business_management`,
  `instagram_content_publish`, `instagram_basic`, `pages_manage_posts`,
  `pages_show_list`, `business_management`, `pages_read_engagement`,
  `public_profile`) están en el carrito de *New requests*, sin enviar.
  El chip "In review" que aparece en la página de onboarding es engañoso.
  Meta pide **dos videos distintos**:

  | Permiso | Video |
  |---|---|
  | `whatsapp_business_messaging` | La app enviando un mensaje, con las dos pantallas: la app enviando y WhatsApp recibiendo el mismo mensaje |
  | `whatsapp_business_management` | Aparte: creando una plantilla + llamadas de prueba a la API |

  Lo que exige no es el Embedded Signup, sino esas dos demostraciones. El
  segundo video es el que obligó a adelantar la Fase 5.

  **Al 2026-08-20** los tres videos están grabados en `AJUSTES/`, hechos contra
  el número **real** (`+52 1 844 341 0326`), no el de prueba:

  | Video | Permisos que sustenta |
  |---|---|
  | `screen-cast-whatsapp.mp4` | `whatsapp_business_messaging` |
  | `screen-cast-plantillas.mp4` | `whatsapp_business_management` |
  | `screen-cast-social.mp4` | los 6 de Facebook/Instagram |

  Las *API test calls* de los 9 permisos ya salen en verde en el panel. Los
  textos en inglés de **Allowed usage** (una descripción por permiso más la
  *Business Description*) están redactados en
  [`AJUSTES/app-review-answers.md`](../AJUSTES/app-review-answers.md).

  Falta únicamente la pestaña **Reviewer instructions**: URL, credenciales de la
  cuenta de `PlatformReviewerSeeder` y los pasos para llegar a Conversaciones,
  Plantillas y Publicación en redes.

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
- ✅ **Verificado en producción el 2026-08-19.** Un mensaje real desde un
  celular recorrió la cadena completa:

  ```
  POST /whatsapp/webhook   200   "facebookexternalua"
  GET  /conversaciones/4   200
  POST /broadcasting/auth  200
  ```

  Eso prueba de una sola pasada que Meta entrega, que la firma valida contra el
  App Secret, que el enrutado por `entry[].id` + `phone_number_id` encuentra la
  cuenta y el número, y que el canal privado de broadcasting autoriza. Hasta
  aquí todo estaba probado solo con HTTP falseado.

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

Configuración — ✅ hecha el 2026-08-17:
- La configuración de Embedded Signup **no existía**: en *Facebook Login for
  Business → Configurations* solo estaba `Publishing - Pages and IG`. Por eso
  no se encontraba el `config_id`; no era que estuviera escondido.
- La que se usa es **`1006528675722697`** (`WhatsApp Embedded Signup`), creada
  a mano con **expiración de token "Never"**. Concede
  `whatsapp_business_messaging` + `whatsapp_business_management` sobre el
  activo *WhatsApp accounts*, con las 7 tareas (`MANAGE` las arrastra todas:
  `DEVELOP`, `MANAGE_TEMPLATES`, `MANAGE_PHONE_ASSETS`, `VIEW_TEMPLATES`,
  `VIEW_PHONE_ASSETS`, `MESSAGING`). Producto: *WhatsApp Cloud API* (+
  *Marketing Messages API*, que Meta marca sola como dependiente).

> **La caducidad del token se fija al crear la configuración y no se puede
> cambiar.** El asistente lo dice en el paso *Access token*: "This can't be
> changed later", y en modo edición los radios salen deshabilitados. Lo mismo
> con *Login variation* y *Products*.
>
> Primero se creó `935973882857232` desde la plantilla *…With 60 Expiration
> Token*, que Meta marca como "(Recommended)". Se descartó porque no hay
> documentado un refresh del token de Embedded Signup que no obligue al
> cliente a rehacer el flujo: serían 15 clientes reonboardeándose cada dos
> meses. La doc de Business Integration System User tokens dice además que el
> default es *never expire* justamente para "offline server-to-server
> communication", que es nuestro caso. Esa configuración quedó sin usar.
>
> El código soporta las dos: el canje guarda `expires_in` en
> `token_expires_at`, y con "Never" queda `null`.

Punto de entrada — ✅ el 2026-08-17:
- La pantalla estaba **huérfana**: existían la ruta y `Connect.vue`, pero
  ningún enlace en toda la interfaz. Solo se llegaba escribiendo la URL.
- Ahora cuelga de `Social/ClientShow`, que es donde el equipo ya va a conectar
  las cuentas de un cliente. Va en **tarjeta aparte**, no como un proveedor más
  de la fila de redes: aquéllos son un redirect de OAuth que devuelve una
  cuenta, éste es Embedded Signup por SDK y devuelve una WABA con N números.
- Gate `Gestionar WhatsApp` en `WhatsAppConnectController` y
  `Gestionar Plantillas WhatsApp` en `WhatsAppTemplateController`. **Antes no
  había ninguno**: el scoping por `client_id` no frena a un usuario interno,
  que lo tiene en null, así que cualquier autenticado podía conectar o
  desconectar la WABA de un cliente escribiendo la URL. Es el mismo fallo que
  ya se había corregido en `SocialController`.

Pendiente de configuración:
- Añadir `WHATSAPP_APP_ID` y `WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID` en producción.
- Mientras la app siga `Unpublished`, Embedded Signup solo lo puede completar
  alguien con rol en la app: en Standard Access, Meta no pide permisos a
  terceros. Publicar la app es lo que abre el flujo a clientes reales.

**Fase 4 — Salida directa a Graph — ✅ hecha el 2026-08-16**
- `WhatsAppService` contra `graph.facebook.com`, con número y token opcionales
  como puerta a multi-WABA.
- Config `services.whatsapp.token` / `phone_number_id` / `business_account_id`.
- Eliminado el bloque `services.n8n` y las variables `N8N_*`, que ya no lee
  nadie.
- 5 tests nuevos: URL y Bearer correctos, `status: read`, número y token
  alternos, `null` sin lanzar ante error 131047, y no llamar a Meta sin config.

**Fase 5 — Ventana de 24h y plantillas — ✅ hecha el 2026-08-17**

Dejó de ser opcional: Meta exige un video **creando una plantilla** para
aprobar `whatsapp_business_management`, y sin UI de plantillas ese video no se
puede grabar. Es prerrequisito de App Review, no un extra.

- Migración y modelo `WhatsAppTemplate`, espejo local de las plantillas de la
  WABA (`meta_id`, estado, componentes, número de variables del cuerpo).
- `WhatsAppTemplateService`: crear, sincronizar y borrar contra
  `/{waba_id}/message_templates`. Va aparte de `WhatsAppService` porque usa
  otro permiso (`whatsapp_business_management`) y otro endpoint. Aquí sí se
  lanzan excepciones: crear una plantilla es una acción explícita y tiene que
  fallar a la vista, al revés que un envío.
- `WhatsAppService::sendTemplate()` con parámetros posicionales.
- `WhatsAppTemplateController` + `WhatsApp/Templates.vue`: listar, crear con
  conteo de `{{n}}` en vivo, sincronizar y borrar. Acotado por `client_id`.
- En Conversaciones, con la ventana cerrada la UI ofrece las plantillas
  **aprobadas** de esa WABA, con vista previa del texto final antes de enviar.
  El mensaje se guarda con el texto ya sustituido: en el hilo tiene que leerse
  lo que recibió el contacto, no `pedido_listo`.
- Webhook `message_template_status_update` → estado y motivo de rechazo. El
  campo ya estaba suscrito en Meta.
- Permiso `Gestionar Plantillas WhatsApp` y entrada en el sidebar.
- 12 tests en `WhatsAppTemplateTest`.

Ya venía de la Fase 2, y se mantiene: `value['statuses']` alimentando
`delivery_status`, el bloqueo del texto libre fuera de la ventana, y el fallo
de entrega visible en la burbuja.

**Había DOS apps de Meta — resuelto el 2026-08-19**

La causa raíz de que el webhook nunca funcionara no era configuración
incompleta: eran **dos apps apuntando al mismo endpoint** de Laravel, que solo
tiene un `WHATSAPP_APP_SECRET`.

| | LunAvalos Social (`1531774538464754`) | LunAvalos Manager (`1602781670818128`) |
|---|---|---|
| Callback URL | mismo endpoint | mismo endpoint |
| `messages` | Subscribed v26.0 | Subscribed v25.0 |
| Token de producción | — | ✅ era de ésta |
| Publicada / Tech Provider / App Review | ✅ | ❌ |

Además el `.env` de producción tenía una pareja imposible:
`WHATSAPP_BUSINESS_ACCOUNT_ID` apuntaba a la **WABA de prueba**
(`987252317374914`) mientras `WHATSAPP_PHONE_NUMBER_ID` apuntaba al **número
real**. Por eso `adoptarWabaPropia` registraba el `+1 555` de Meta.

Consolidado en **LunAvalos Social**:

- Se quitó el Callback URL y la suscripción a `messages` de `LunAvalos Manager`
  (reversible: el verify token es el mismo que `WHATSAPP_VERIFY_TOKEN`).
- Token nuevo del system user `whatsapp-api` emitido **para LunAvalos Social**,
  sin caducidad, con los dos permisos. Verificado antes de desplegar con
  `GET /debug_token`, que devolvió `app_id: 1531774538464754`,
  `application: LunAvalos Social` y `expires_at: 0`.

  > El `debug_token` es la comprobación que hay que hacer **siempre** al rotar
  > este token. Un token válido emitido para la app equivocada no falla al
  > leer: falla al suscribir, y el síntoma aparece días después como "no llegan
  > mensajes".

- **Asignar la WABA al system user, explícitamente.** El acceso Admin sobre el
  portfolio basta para las lecturas, pero no para escribir: `DELETE
  /{waba_id}/message_templates` devolvía
  `(#100) Need permission on either WhatsApp Business Account or owner/shared
  business` hasta que se asignó `LunAvalos` como activo de `whatsapp-api` en
  Business Settings. Curiosamente el `POST` de creación sí pasaba.
- `WHATSAPP_BUSINESS_ACCOUNT_ID=2436841820155807` (WABA `LunAvalos`, número
  `+52 1 844 341 0326` / `1230737580126123`, calidad GREEN).
- `WHATSAPP_APP_SECRET` ya era el correcto: coincide con `FACEBOOK_CLIENT_SECRET`
  de la app 1531774538464754.

> La app `LunAvalos Manager` **no se borró**: conserva el número registrado y el
> método de pago, y sirve de vuelta atrás. Borrarla es irreversible y no urge.

> Higiene pendiente: `EVIEWER_EMAIL` en producción está mal escrito (falta la R);
> hoy funciona solo porque el seeder tiene ese mismo correo por omisión.
> `WHATSAPP_API_VERSION` sigue en Preview y no la lee nadie.

**La WABA propia no pasa por Embedded Signup — resuelto el 2026-08-19**

Al intentar conectar la WABA de LunAvalos por Embedded Signup, el desplegable
*WhatsApp Business account* solo ofrece `Create a WhatsApp Business account`:
la WABA existente **no aparece**. El motivo es de diseño, no un fallo — ese
flujo existe para que un negocio ajeno nos comparta su cuenta, y cuando el
portfolio dueño de la app es el mismo (`LunAvalos Manager`) no hay nada que
conceder.

Consecuencia: sin otro camino, nuestro propio número no tiene fila en
`whatsapp_accounts`, el webhook descarta sus mensajes por venir de una "WABA
desconocida" y la pantalla de plantillas se ve vacía.

- `WhatsAppOnboardingService::adoptarWabaPropia()` y el comando
  `php artisan whatsapp:adoptar-waba-propia` (con `--dry-run`): leen
  `WHATSAPP_BUSINESS_ACCOUNT_ID` y `WHATSAPP_TOKEN`, sincronizan los números y
  hacen `POST /{waba_id}/subscribed_apps`. Idempotente.
- **El token no se guarda en la fila**: `tokenParaEnviar()` cae al del system
  user en configuración. Guardarlo duplicaría el secreto sin ganar nada.
- `client_id` queda en **null**, que es lo que significa "número propio" (§4).
- 6 tests en `WhatsAppOwnWabaTest`, incluido el que cierra el círculo: tras
  registrarla, un evento entrante deja de descartarse y abre conversación.

> Ojo con los otros portfolios que aparecen en ese desplegable
> (`Lavanda Pastelería`, `Salsas La Querendona`, `Suplementos Elegue Mx`…).
> Elegir uno crearía una WABA **dentro del negocio de ese cliente**. No se hace
> sin su consentimiento.

**Tres defectos que solo aparecieron contra la API real — 2026-08-20**

Los tres estaban cubiertos por tests en verde. Ninguno de esos tests miraba lo
que de verdad importaba, y es el patrón que conviene recordar: **HTTP falseado
comprueba que llamas, no que llamas bien.**

*1. Las plantillas se creaban y Meta las rechazaba horas después.*
`sitio_web_listo` volvió como `REJECTED` con motivo `INVALID_FORMAT`. Faltaba
`example.body_text`: Meta exige un valor de ejemplo por cada `{{n}}` para que su
revisor entienda el hueco. Sin él la creación **se acepta** y el rechazo llega
en la revisión — el peor modo de fallo, porque parece que funcionó.

- `componentesDesde()` manda ahora `example.body_text`.
- El controlador valida que haya exactamente un ejemplo por variable **antes**
  de llamar a Meta, y rechaza variables en el encabezado (la API las admite,
  pero con su propio bloque de ejemplos; no compensa el modo de fallo extra).
- El formulario pide los ejemplos, explica qué son los huecos y muestra una
  **vista previa** del mensaje ya sustituido, que enseña el concepto mejor que
  cualquier texto de ayuda.

*2. El borrado de plantillas nunca funcionó.*
Laravel manda los datos de un `DELETE` en el **cuerpo**, y Meta espera `name` en
la **query**: la petición salía sin nombre. El test que existía comprobaba que
la fila local desapareciera y que se llamara a Meta, pero **nunca miró qué se le
mandaba**, así que pasaba en verde. El test nuevo mira la URL.

*3. `sincronizar()` no podaba.* Una plantilla borrada en Meta —o en el Business
Manager del cliente— seguía visible aquí para siempre, y se ofrecía al
responder. Ahora se poda, pero **solo si la respuesta cupo entera**: si viniera
llena podría haber más páginas, y lo que falta no sería "borrado en Meta" sino
"no lo pedimos".

**El tiempo real de la bandeja — 2026-08-20**

`ConversationController` emitía `ConversationMessageSent` al responder, pero
`WhatsAppWebhookController` **no emitía nada**: el tiempo real cubría solo lo que
salía de la app, y un mensaje entrante exigía recargar. Justo lo contrario de
para lo que sirve una bandeja.

- El webhook emite ahora `ConversationMessageSent` al guardar el entrante, **sin
  `toOthers()`**: no lo originó ningún navegador, así que no hay a quién excluir.
- Y `ConversationUpdated`, un evento nuevo para la **lista**. El anterior viaja
  por `conversation.{id}` y solo lo oye quien tiene esa conversación abierta.
- Dos canales, porque los dos públicos son distintos: el staff interno ve todo y
  escucha `conversations.internal`; un usuario de portal escucha solo
  `conversations.client.{id}`. Una conversación de número propio va únicamente
  al interno — no hay cliente a cuyo canal mandarla.
- En el front la lista pasó a ser local y mutable: inserta o reemplaza la fila,
  reordena por fecha, fuerza a cero el contador de la conversación abierta, y
  quita la fila si deja de encajar en el filtro.

> **Los canales van como clases, no como closures**, y no es cosmético: en los
> tests `BROADCAST_CONNECTION=null`, y el driver nulo **autoriza cualquier canal
> sin consultar la regla**. Las primeras pruebas contra `/broadcasting/auth`
> pasaban todas, incluidas las que debían fallar. `InternalInboxChannel` y
> `ClientInboxChannel` se prueban directamente.
>
> El canal `conversation.{id}` sigue siendo una closure y por tanto **sigue sin
> estar probado**. Está en la misma situación.

Suite completa: **177 tests**.

**Cuenta de revisión — actualizada el 2026-08-19**

`PlatformReviewerSeeder` se había quedado en el modelo anterior: daba solo
permisos de Social y Tickets, y su dato de demo era un ticket con
`whatsapp_wa_id`. Un revisor de Meta entraba y **no podía evaluar ninguno de los
dos permisos de WhatsApp**.

- Se le añaden `Ver Conversaciones`, `Responder Conversaciones`,
  `Gestionar WhatsApp` y `Gestionar Plantillas WhatsApp`. Los cuatro siguen
  acotados al cliente demo por `users.client_id`.
- Fixtures nuevos: WABA y número demo, una conversación con la **ventana
  abierta** (cerrada mostraría el caso excepcional, no el normal) y una
  plantilla aprobada para que la pantalla de plantillas no se vea vacía.
- La cuenta demo lleva un `access_token` de relleno **a propósito**:
  `tokenParaEnviar()` cae al token de producción cuando la columna está vacía,
  así que dejarlo nulo haría que un intento de respuesta del revisor saliera
  con nuestras credenciales reales. Hay un test que lo verifica.
- 5 tests en `PlatformReviewerSeederTest`.

**Fase 6 — API de plataforma — ✅ hecha el 2026-08-21**

Cambio de plan respecto al borrador: la Fase 6 era "agente de IA por n8n". Al
plantearse conectar **klwebapp** y las **landings de Grupo Macadam**, la
auditoría encontró que `routes/api.php` **no existía** — Sanctum estaba en el
`composer.json` sin un solo `HasApiTokens`. Es decir: ningún sistema externo
podía usar este WhatsApp, y n8n no lo arreglaba, porque n8n no conoce los
clientes, ni los tokens, ni la ventana de 24 h.

Sin esta capa el agente de IA solo habría servido dentro del admin. Con ella,
sirve para todos los sistemas. Por eso se adelantó.

- `ApiConsumer` + tokens de Sanctum con habilidades. El alcance sale de
  `api_consumers.client_id`, **no** del cuerpo de la petición: mismo criterio
  que `users.client_id` en la UI, y mismo `null` = LunAvalos de §4.
- `POST /api/v1/mensajes` y `/mensajes/plantilla`, `GET /plantillas`,
  `/conversaciones`, `/conversaciones/{id}` y `/yo`.
- **`ConversationSender`**: el envío que antes vivía duplicado en
  `ConversationController::reply()` y `replyTemplate()` pasa a un servicio que
  usan la UI, la API y —cuando llegue— el agente de IA. Los tres respetan la
  ventana, guardan el `delivery_status` real y emiten los dos eventos de tiempo
  real, porque es un solo camino.
- `NotifyApiConsumers`: entrega los entrantes a los sistemas suscritos, firmada
  con HMAC sobre el cuerpo crudo — mismo formato con el que Meta nos firma a
  nosotros. Sin secreto no se entrega: mejor nada que sin firmar.
- `php artisan api:consumidor` para dar de alta integraciones. El token en
  claro sale por consola una vez, no por una pantalla web.
- 20 tests en `PlatformApiTest`. Suite completa: **197**.
- Documentación de integración: [`docs/api-plataforma.md`](api-plataforma.md).

> Falta correr `php artisan migrate` (crea `api_consumers` y
> `personal_access_tokens`) y registrar las integraciones reales.

**Fase 7 — Agente de IA por cliente — ✅ backend hecho el 2026-08-21**

Decisión tomada el 2026-08-21, después de comparar contra hacerlo en n8n:
**el agente corre en Laravel contra la API de Claude**, y n8n se queda para los
conectores (Meta Ads, Google Ads, terceros), fuera del camino crítico.

Los cuatro motivos, para que no se reabra:

1. Un agente por cliente en n8n *es* un workflow por cliente, que §3.3 ya
   descartó.
2. Los límites de consumo exigen contar tokens en una tabla. n8n no tiene dónde.
3. El agente necesita el contexto del cliente —y ya está capturado en
   `clients.briefing_context`, `briefing_target_audience`, `briefing_competitors`,
   `briefing_contact_methods`—. Desde n8n habría que exponer una API interna y
   sacar datos de clientes finales de terceros a otro servidor en cada mensaje.
4. Responderle a un cliente final es lo único con reloj; un salto de red más ahí
   es un modo de falla que no compra nada.

Modelo de cobro elegido: **una sola cuenta de Anthropic de LunAvalos, con tope
por cliente** (~$3–14 USD por cada 1,000 mensajes según el modelo, y bastante
menos con prompt caching del contexto del cliente, que no cambia entre
mensajes). Se deja la columna `api_key` nullable por si algún día un cliente
grande trae la suya.

**Backend — ✅ hecho el 2026-08-21:**
- `AiAgent` (uno por cliente, `client_id` nullable único) y `AiUsage`
  (consumo agregado por agente y mes).
- `ClaudeGateway` + `AnthropicGateway`: **todo el uso del SDK en un archivo**.
  El SDK trae su propio cliente HTTP, así que `Http::fake()` no lo alcanza —
  sin esta frontera los tests del agente saldrían a red de verdad.
- `ConversationAgent`: arma el prompt, el historial y envía por
  `ConversationSender` con `author_type: ai`, que la UI ya sabe pintar.
- `ResponderConIA` en cola, con **un solo intento**: un reintento llegaría
  tarde y arriesgaría mandarle dos respuestas al contacto.
- `php artisan ai:agente` para encender un agente y ver su consumo.
- 19 tests en `ConversationAgentTest`. Suite completa: **216**.

Decisiones que conviene no reabrir:

- **La IA se calla si alguien tomó la conversación** (`assigned_id` no nulo).
  Se comprueba dos veces —al despachar y dentro del job— porque entre una y
  otra pasan segundos reales, y en esos segundos alguien del equipo puede
  abrirla. Asignarse una conversación pasa a ser el modo de apagar el agente
  sobre la marcha.
- **El prompt sale de la ficha del cliente** (`briefing_context`,
  `briefing_target_audience`, `briefing_contact_methods`), que ya estaba
  capturada. `system_prompt` propio la sobreescribe.
- **El prompt va en bloque cacheado y no lleva nada variable dentro.** Ni
  fecha ni nombre del contacto: un byte distinto invalida la caché y el ahorro
  desaparece sin avisar.
- **Los tokens leídos de caché no cuentan para el tope.** Cuestan ~10%, y
  hacerlos contar castigaría justo lo que abarata el agente.
- **El consumo se registra aunque el modelo decline o no devuelva texto.**
  Anthropic lo cobra igual; un tope que no cuenta lo gastado no es un tope.
- **Un fallo de la API no relanza el job.** El resultado correcto es "sin
  respuesta automática", no una conversación rota y un job en fallidos.
- **`effort: 'low'`.** Contestar un WhatsApp no es razonamiento profundo, y
  además produce respuestas más cortas — que es lo que se quiere en el canal.

Pendiente:
- **Pantalla de agentes.** Hoy solo hay comando. Hace falta para que el equipo
  ajuste el prompt sin pedir consola — que era, precisamente, el único
  argumento real a favor de n8n.
- `ANTHROPIC_API_KEY` en producción.
- Que el aviso de automatización aparezca también en la ficha del cliente, no
  solo en el primer mensaje.
- n8n: conectores de ads y automatizaciones no conversacionales, llamando a la
  API de la Fase 6.

## 10. Config

```env
# Entrada (ya en producción)
WHATSAPP_APP_ID=1531774538464754
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_GRAPH_VERSION=v26.0

# Salida (ya en producción)
# ✅ Corregido el 2026-08-20: producción usa el número REAL
#     +52 1 844 341 0326 / 1230737580126123 (calidad GREEN)
# y con él se hicieron las pruebas y se grabaron los videos de App Review.
#
# Histórico — el 2026-08-19 producción tenía los valores del número de PRUEBA
# que Meta regala (WHATSAPP_BUSINESS_ACCOUNT_ID=987252317374914,
# WHATSAPP_PHONE_NUMBER_ID=1201903109667621, +1 555 628-6220). Ese número solo
# entrega a 5 destinatarios dados de alta a mano y no sirve para operar; se
# deja anotado porque explica por qué `adoptarWabaPropia` registraba el +1 555.
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=1230737580126123
WHATSAPP_BUSINESS_ACCOUNT_ID=2436841820155807
WHATSAPP_TIMEOUT=10

# Fase 3 — creada en el panel el 2026-08-17, token sin caducidad
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=1006528675722697
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

  Dos que aplican concretamente aquí:

  - **Que lo declarado no coincida con las llamadas reales.** Al redactar
    `AJUSTES/app-review-answers.md` se afirmaba que leíamos el feed de la página
    (`GET /{page-id}/feed`) y que borrábamos posts vía Graph. El código no hace
    ninguna de las dos: solo publica. Se corrigió. Merece la pena grepear los
    endpoints antes de declarar un uso.
  - **Que el revisor no pueda reproducirlo.** La cuenta demo cuelga de una WABA
    ficticia, así que un envío o una creación de plantilla fallan contra Graph.
    Las instrucciones lo dicen de frente en vez de dejar que lo descubra: un
    revisor que ve algo romperse sin explicación rechaza.

- **El estado de la conversación demo caduca.** El seeder pone `last_inbound_at`
  a 30 minutos atrás *en el momento de correrlo*. Si Meta revisa tres días
  después, la ventana estará cerrada. Las instrucciones describen los dos
  estados como intencionales para no depender de eso.

## 12. Pendientes de decisión

- **¿El número de Macadam va bajo la WABA de LunAvalos o Macadam tendrá la
  suya?** Determina si el piloto sale con Standard Access esta semana o espera
  a Advanced Access.
- ¿El número actual `+52 1 844 341 0326` se queda como el de LunAvalos, o migra?
- ¿Los clientes ven su conversación en el portal, o solo el staff?
  (El esquema lo soporta: `conversations.client_id` + permiso de Spatie.)
- ¿El agente de IA responde siempre, o solo fuera de horario / cuando nadie
  toma la conversación?
