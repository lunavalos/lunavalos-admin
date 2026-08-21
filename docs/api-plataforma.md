# API de plataforma — v1

> Para que otros sistemas (klwebapp, las landings de Grupo Macadam, workflows de
> n8n) usen el WhatsApp que administra esta aplicación.
>
> La alternativa —darle a cada sistema su propia app de Meta— multiplicaría las
> App Reviews, los números y los quality ratings, y repartiría copias de los
> tokens de los clientes. Aquí el token de Meta no sale nunca de este servidor.

## Por qué existe

`lunavalos-admin` ya es la plataforma de WhatsApp: tiene las WABAs, los tokens
cifrados, las conversaciones, las plantillas y la ventana de 24 h. Lo que no
tenía era puerta de entrada para otros sistemas.

Un sistema externo **no** habla con Graph: habla con esta API, y ésta decide
desde qué número sale, si la ventana permite texto libre y qué se guarda en el
hilo. Por eso un mensaje mandado por klwebapp aparece en la bandeja del admin
como cualquier otro, y el equipo ve una sola conversación por contacto en vez
de dos historiales que no cuadran.

## Autenticación

Token Bearer de Sanctum, emitido a un **ApiConsumer** (un sistema, no un
usuario):

```
Authorization: Bearer 3|xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Alta de una integración:

```bash
# Atada a un cliente: solo puede operar sobre él.
php artisan api:consumidor "landing-macadam" \
    --client=12 \
    --webhook=https://macadam.mx/api/whatsapp/entrante

# Interna de LunAvalos: opera sobre varios clientes y debe nombrar
# `client_id` en cada petición.
php artisan api:consumidor "klwebapp" \
    --webhook=https://klwebapp.mx/api/whatsapp/entrante

# Solo lectura.
php artisan api:consumidor "tablero" --client=12 --permisos=conversaciones:leer
```

El token en claro **se muestra una sola vez**: Sanctum guarda su hash. Si se
pierde, se emite otro.

### Permisos

| Habilidad | Qué abre |
|---|---|
| `mensajes:enviar` | `POST /mensajes`, `POST /mensajes/plantilla` |
| `conversaciones:leer` | `GET /conversaciones`, `GET /conversaciones/{id}` |
| `plantillas:leer` | `GET /plantillas` |

`GET /yo` no pide ninguna: es lo que se consulta justamente cuando algo falla.

### Alcance

Sale de `api_consumers.client_id`, **no** de lo que mande el llamador:

- **Atado** (`client_id` con valor) → solo su cliente. Mandar el `client_id` de
  otro no sirve de nada; se ignora.
- **Interno** (`client_id` null) → puede operar sobre cualquier cliente, pero
  tiene que decir sobre cuál en cada petición.

## Endpoints

Base: `https://admin.lunavalos.com/api/v1`

### `GET /yo`

Qué es este token y sobre qué pega. Primera parada al integrar.

```json
{
  "integracion": { "nombre": "landing-macadam", "slug": "landing-macadam", "estado": "active" },
  "alcance": { "atado": true, "client_id": 12, "cliente": "Grupo Macadam" },
  "token": { "nombre": "landing-macadam-20260821", "permisos": ["mensajes:enviar"], "expira_el": null },
  "numeros": [{ "phone_number_id": "1230737580126123", "display_phone_number": "+52 1 844 341 0326", "quality_rating": "GREEN" }],
  "recibe_webhooks": true
}
```

### `POST /mensajes` — texto libre

```json
{ "to": "+52 1 844 341 0326", "body": "Ya está listo tu pedido" }
```

`to` acepta cualquier formato: se normaliza a dígitos. Sin eso, el mismo
contacto abriría una conversación distinta por cada formato que llegue.

**Solo funciona dentro de la ventana de 24 h.** Fuera de ella devuelve
`422 ventana_cerrada` **sin llamar a Meta y sin guardar nada** — es la regla de
Meta, no nuestra, y tragársela es lo que hacía que un mensaje se guardara como
enviado sin que el contacto lo recibiera.

Respuesta `201`:

```json
{
  "id": 481,
  "conversation_id": 37,
  "wa_message_id": "wamid.HBgM...",
  "type": "text",
  "body": "Ya está listo tu pedido",
  "delivery_status": "sent",
  "delivery_error": null,
  "created_at": "2026-08-21T02:14:09+00:00"
}
```

> **`delivery_status` es lo que importa, no el 201.** El 201 dice que lo
> registramos; `sent` dice que Meta lo aceptó. Si sale `failed`, el mensaje
> quedó en el hilo pero el contacto no lo recibió, y `delivery_error` dice por
> qué.

### `POST /mensajes/plantilla` — plantilla aprobada

```json
{ "to": "5218443410326", "template_id": 8, "parametros": ["Ana", "viernes"] }
```

Funciona dentro y fuera de la ventana, y **es el único camino para iniciar una
conversación**: un contacto que nunca te ha escrito no tiene ventana abierta.
Es el endpoint que usan las landings para el primer contacto de un lead.

`parametros` van en orden: el primero sustituye `{{1}}`, el segundo `{{2}}`.
Tiene que haber exactamente tantos como `body_variables`.

El mensaje se guarda con el texto **ya sustituido**: en el hilo tiene que
leerse lo que recibió el contacto, no `lead_recibido`.

### `GET /plantillas`

Las que se pueden enviar, con su contrato:

```json
{
  "data": [{
    "id": 8, "name": "lead_recibido", "language": "es_MX",
    "category": "UTILITY", "status": "APPROVED",
    "body": "Hola {{1}}, recibimos tu solicitud. Te contactamos el {{2}}.",
    "body_variables": 2
  }],
  "numero": { "phone_number_id": "1230737580126123", "display_phone_number": "+52 1 844 341 0326", "quality_rating": "GREEN" }
}
```

Por omisión solo las `APPROVED` — las demás solo producen envíos fallidos.
Con `?todas=1` salen todas, que es lo que hace falta para responder "¿por qué
no aparece la que acabo de crear?".

### `GET /conversaciones` · `GET /conversaciones/{id}`

Lista paginada e hilo. Cada conversación trae `ventana_abierta`, para que el
llamador sepa si puede mandar texto libre sin replicar la regla de las 24 h.

Filtros: `status` (`open|snoozed|archived`), `contacto` (teléfono en cualquier
formato), `por_pagina`.

## Webhooks salientes

Con `--webhook`, cada mensaje entrante se entrega por `POST`:

```
POST https://macadam.mx/api/whatsapp/entrante
X-LunAvalos-Event: mensaje.entrante
X-LunAvalos-Signature: sha256=a3f1...
```

```json
{
  "evento": "mensaje.entrante",
  "ocurrido_el": "2026-08-21T02:14:09+00:00",
  "conversacion": {
    "id": 37, "client_id": 12,
    "contact_wa_id": "5218443410326", "contact_name": "Ana",
    "ventana_abierta": true
  },
  "mensaje": {
    "id": 482, "wa_message_id": "wamid.HBgM...",
    "author_type": "contact", "direction": "in",
    "type": "text", "body": "¿A qué hora abren?"
  }
}
```

### Verificar la firma

Mismo formato que usa Meta con nosotros, sobre el **cuerpo crudo**:

```php
$esperada = 'sha256=' . hash_hmac('sha256', $cuerpoCrudo, $secreto);

if (!hash_equals($esperada, $request->header('X-LunAvalos-Signature'))) {
    abort(401);
}
```

Sobre el cuerpo crudo, no sobre el JSON reserializado: cualquier diferencia de
orden o de escapes rompe la firma. Es el mismo detalle que ya costó tiempo en
el webhook de entrada.

**A quién se entrega:** misma regla que para enviar. Un consumidor atado recibe
solo lo de su cliente; uno interno de LunAvalos recibe todo. Un consumidor sin
secreto **no recibe nada** — mejor no entregar que entregar sin firmar.

Tres intentos con espera de 10 s y 60 s. Un endpoint caído no arrastra a los
demás consumidores.

## Errores

Siempre con la misma forma:

```json
{ "error": { "code": "ventana_cerrada", "message": "La ventana de 24 horas está cerrada…" } }
```

| Código | HTTP | Qué hacer |
|---|---|---|
| `ventana_cerrada` | 422 | Reintentar con `POST /mensajes/plantilla` |
| `plantilla_no_disponible` | 422 | La plantilla no existe, no está aprobada, o faltan parámetros |
| `cliente_requerido` | 422 | Integración interna: manda `client_id` |
| `numero_ambiguo` | 422 | El cliente tiene varios números: manda `phone_number_id` |
| `numero_no_encontrado` | 404 | No hay número activo, o no es de este cliente |
| `consumidor_inactivo` | 403 | La integración está desactivada |
| `token_invalido` | 403 | El token no es de una integración |

> `numero_no_encontrado` es el mismo mensaje exista o no exista el número en
> otro cliente: "no es tuyo" y "no existe" no deben distinguirse desde fuera.

## Ejemplo: un lead de landing

```bash
# 1. Qué plantillas hay
curl -s https://admin.lunavalos.com/api/v1/plantillas \
     -H "Authorization: Bearer $TOKEN"

# 2. Primer contacto — por plantilla, porque no hay ventana abierta
curl -s -X POST https://admin.lunavalos.com/api/v1/mensajes/plantilla \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"to":"+52 844 123 4567","template_id":8,"parametros":["Ana","viernes"]}'

# 3. Ana contesta → llega tu webhook con ventana_abierta: true
# 4. Ya se le puede escribir libre durante 24 h
curl -s -X POST https://admin.lunavalos.com/api/v1/mensajes \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"to":"+52 844 123 4567","body":"Abrimos de 9 a 6, Ana"}'
```

## Lo que esta API no hace

- **Crear plantillas.** Usa `whatsapp_business_management` y arrastra el flujo
  de aprobación de Meta con sus ejemplos por variable. Es operación, no
  integración: vive en la pantalla del admin.
- **Conectar WABAs.** Embedded Signup necesita un navegador y la cuenta de
  Facebook del cliente.
- **Saltarse la ventana de 24 h.** Ningún token la abre.

## Implementación

| Pieza | Dónde |
|---|---|
| Rutas | `routes/api.php` |
| Alcance y errores | `app/Http/Controllers/Api/ApiController.php` |
| Envíos | `app/Http/Controllers/Api/MessageController.php` |
| Envío compartido con la UI | `app/Services/WhatsApp/ConversationSender.php` |
| Webhooks salientes | `app/Jobs/NotifyApiConsumers.php` |
| Alta de integraciones | `app/Console/Commands/CreateApiConsumer.php` |
| Tests | `tests/Feature/PlatformApiTest.php` (20) |

`ConversationSender` es la pieza que evita el problema de fondo: la UI del
admin, esta API y —cuando llegue— el agente de IA mandan por el mismo camino,
así que los tres respetan la ventana, guardan el estado de entrega real y
emiten los dos eventos de tiempo real. Tenerlo tres veces garantizaba que
alguno se quedara sin alguna de las tres cosas.
