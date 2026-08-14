# Plan de migración: WhatsApp multi-WABA (Tech Provider)

> Estado del documento: **propuesta**, no implementado.
> Escrito el 2026-08-13 tras auditar el código, la instancia de n8n y el panel de Meta.
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
| Access Verification (Tech Provider) | ❌ **Sin iniciar**, fecha límite **10/12/2026** |
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

Automatizaciones (opcional, desacoplado):
  Laravel ──evento──► n8n ──► lo que el cliente necesite
```

### Decisión: n8n sale del camino transaccional

La justificación original de n8n era la custodia del token. Con multi-WABA
**Laravel tiene que custodiar los tokens de todos modos**, porque Laravel es
quien corre el onboarding. Mantener n8n en medio agregaría latencia y un modo
de falla sin ganar nada en seguridad.

**n8n se queda**, pero para lo que sí hace bien: automatizaciones por cliente
que se arman sin desplegar código (auto-respuestas, ruteo, sincronización con
CRM, reportes). Laravel le manda eventos; n8n no toca tokens de Meta.

Esto además elimina el plan de "duplicar el workflow por cliente" del README
viejo, que no aplica: con multi-WABA **todos** los eventos llegan al mismo
Callback URL y se distinguen por `entry[].id`.

## 4. Esquema de base de datos

Precedente a seguir: `SocialAccount`, que ya es per-`client_id` con token y
expiración. Dos diferencias: aquí **sí** ciframos el token, y agregamos el
número como entidad de primera clase.

```php
// create_whatsapp_accounts_table
Schema::create('whatsapp_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();

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

Y en `tickets`, para saber por qué número entró la conversación:

```php
$table->foreignId('whatsapp_number_id')->nullable()->constrained();
```

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

**`receive()`** — el cambio de fondo es enrutar por WABA:

```php
foreach ($request->input('entry', []) as $entry) {
    $account = WhatsAppAccount::where('waba_id', $entry['id'] ?? '')->first();
    if (!$account) { continue; }   // WABA que ya no administramos
    // ...resolver el número por metadata.phone_number_id y crear/actualizar
    //    el ticket dentro del scope de $account->client_id
}
```

Todo lo bueno que ya existe se conserva: idempotencia por `wa_message_id`,
respuesta 200 rápida, y el emparejamiento de cliente por teléfono — pero ahora
acotado a `$account->client_id`, no global.

## 7. Salida

`WhatsAppService` deja de ser un cliente de n8n y pasa a hablar con Graph,
recibiendo siempre el número desde el que se envía:

```php
public function sendText(WhatsAppNumber $number, string $to, string $message): ?string
```

El token sale de `$number->account->access_token`. La URL es
`https://graph.facebook.com/v2X.0/{$number->phone_number_id}/messages`.

Se mantiene el criterio actual, que es correcto: **un fallo de envío nunca debe
tumbar la petición que lo originó**.

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

**Fase 0 — Trámites (empieza hoy, bloquea todo lo demás)**
- Access Verification / Tech Provider. Fecha límite **10/12/2026**.
- App Review con **Advanced Access** en los dos permisos de WhatsApp.
- Publicar la app.

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

**Fase 2 — Esquema multi-tenant**
- Migraciones y modelos `WhatsAppAccount` / `WhatsAppNumber`.
- Backfill del número actual (`waba_id 2436841820155807`,
  `phone_number_id 1230737580126123`) como primer registro.
- Enrutado por `entry[].id` en el webhook.

**Fase 3 — Embedded Signup**
- Página de conexión, canje de code, `subscribed_apps`, reconexión y revocación.

**Fase 4 — Salida multi-número**
- `WhatsAppService` contra Graph, token por cliente.
- Retiro de `N8N_WHATSAPP_WEBHOOK_URL` y `N8N_SHARED_SECRET`.

**Fase 5 — Ventana de 24h y plantillas**
- Estados de entrega, `last_inbound_at`, plantillas y señal de fallo en la UI.

**Fase 6 — n8n como capa de automatización**
- Definir el contrato de eventos Laravel → n8n. Sin tokens de Meta de por medio.

## 10. Config

```env
# Reemplazan a N8N_WHATSAPP_WEBHOOK_URL / N8N_SHARED_SECRET
WHATSAPP_APP_ID=1531774538464754
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=
WHATSAPP_GRAPH_VERSION=v26.0
```

> Meta ya va en `v26.0`; los workflows del repo piden `v23.0`.

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

- ¿Administramos el business portfolio de algún cliente, o cada quien el suyo?
  (Lo pregunta el formulario de Access Verification.)
- ¿El número actual `+52 1 844 341 0326` se queda como el de LunAvalos, o migra?
- ¿Los clientes ven su conversación en el portal, o solo el staff?
