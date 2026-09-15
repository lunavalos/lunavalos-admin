# Avisos de ticket por WhatsApp

> Escrito el 2026-09-14, cuando se implementó.
> Para qué sirve este documento: dentro de seis meses, cuando el aviso deje de
> llegar, esto dice dónde mirar y por qué está hecho así.

## En una frase

Cuando un ticket cambia de **estado** o de **responsable**, sale un WhatsApp
desde el número de LunAvalos hacia un teléfono configurado, usando una
**plantilla aprobada**, y el aviso queda registrado en el módulo de
Conversaciones como cualquier otro mensaje.

## Lo que NO usa, y por qué importa

**No usa la API de plataforma ni ninguna credencial.** No hace falta emitir un
`ApiConsumer` ni un token de Sanctum para esto.

La API de `docs/api-plataforma.md` existe para que sistemas **externos**
(klwebapp, las landings, n8n) usen este WhatsApp sin tener tokens de Meta. Los
tickets viven en esta misma aplicación: pedirle a Laravel que se llame a sí
mismo por HTTP con un Bearer token añadiría un secreto que rotar, una llamada
de red que puede fallar y un punto más donde autenticarse, a cambio de nada.

> **Regla:** dentro de la app se usa `ConversationSender` directamente. Las
> credenciales son para lo que vive fuera del servidor. Si algún día un aviso
> tiene que dispararlo un sistema ajeno, ese sí pasa por la API — y entonces
> hay que arreglar antes el bug de `ApiController::clienteId()` descrito abajo.

## Las piezas

| Archivo | Qué hace |
|---|---|
| `app/Observers/TicketObserver.php` | Decide **cuándo** avisar |
| `app/Jobs/NotifyTicketUpdate.php` | Decide **qué** se manda y **a quién** |
| `app/Services/WhatsApp/ConversationSender.php` | Lo envía y lo deja en el hilo |
| `config/services.php` → `whatsapp.ticket_alerts` | Lo enciende o lo apaga |
| `tests/Feature/TicketWhatsAppAlertTest.php` | Lo que se espera de todo esto |

El observer se registra en `AppServiceProvider::boot()`.

## El flujo, paso a paso

```
Alguien mueve una tarjeta del Kanban
  └─ POST /tickets/{id}/status  (o /assign, /service, /cycle, /start-work, PUT /tickets/{id})
       └─ $ticket->save()
            └─ TicketObserver::updated()
                 ├─ ¿está encendido? (si no, termina aquí y no encola nada)
                 ├─ ¿cambió `status` o `assigned_id`? (si no, termina aquí)
                 └─ NotifyTicketUpdate::dispatch()  ──► cola `database`
                                                          │
  El request ya respondió. El ticket está guardado.       │
                                                          ▼
                                            NotifyTicketUpdate::handle()
                                              ├─ número propio  (client_id null, activo)
                                              ├─ plantilla por NOMBRE en esa WABA
                                              ├─ ConversationSender::resolverConversacion()
                                              └─ ConversationSender::enviarPlantilla()
                                                   ├─ POST a Graph /{phone_number_id}/messages
                                                   ├─ guarda el ConversationMessage
                                                   └─ broadcast a la bandeja
```

### Por qué cada decisión está donde está

**Un observer, no seis llamadas en el controlador.** "Actualizar un ticket" son
seis endpoints (`updateStatus`, `assign`, `updateService`, `updateCycle`,
`startWork`, `update`) más lo que toque el Kanban al arrastrar. Poner el aviso
en cada uno era garantía de olvidarse de alguno el día que se añada el séptimo.

**Solo estado y responsable.** Notificar cualquier `updated` convertiría el
aviso en ruido: cada guardado de título, cada recálculo de crédito, cada
`status_updated_at` que el propio modelo toca en `boot()`. Y en WhatsApp el
ruido no se ignora —se silencia el número entero, y entonces no llega tampoco
lo que sí importaba—. Además cada aviso es una conversación de utilidad que se
factura.

**En cola, no en el request.** Un fallo al avisar no puede tumbar la petición
que movió el ticket. Se arrastra una tarjeta y eso tiene que guardarse aunque
Meta esté caído. Requiere `php artisan queue:work` corriendo.

**Plantilla, nunca texto libre.** Es la regla del §8 de
`docs/whatsapp-multi-waba.md` aplicada al primer caso que la necesita de
verdad: fuera de la ventana de 24 h, Meta rechaza el texto libre con **131047**.
Y un aviso interno nace *siempre* fuera de la ventana —nadie le escribe a su
propio sistema de tickets—, así que `sendText()` fallaría el 100 % de las veces.
`Conversation::ventanaAbierta()` mide desde `last_inbound_at`, y en esta
conversación ese campo no se llena nunca.

**Por nombre, no por id.** La plantilla se busca por `name`. Si se configurara
por id, recrear la plantilla en Meta cambiaría el id y el aviso moriría en
silencio: sin error visible, simplemente dejarían de llegar.

**Pasa por `ConversationSender`.** El mismo que usa la bandeja del admin. Por
eso el aviso aparece en Conversaciones con su `delivery_status` y su hora, en
vez de ser un envío fantasma del que no queda rastro. Es el mismo criterio que
ya se aplicó a la API: que no existan dos caminos de envío con dos historiales
que no cuadran.

**`author_type = 'system'`.** En el hilo se pinta como «· automático». Sin eso
se leería como si alguien del equipo lo hubiera escrito a mano.

## Configuración

```env
# Apagado mientras falte cualquiera de los dos.
WHATSAPP_TICKET_ALERT_TO=528442751165
WHATSAPP_TICKET_ALERT_TEMPLATE=ticket_actualizado
```

**`WHATSAPP_TICKET_ALERT_TO` va internacional y solo dígitos.** Con
`8442751165` se abre una conversación con un wa_id roto y no sale nada. El
código quita lo que no sea dígito, pero no puede adivinar el código de país.

**`WHATSAPP_TICKET_ALERT_TEMPLATE` es el nombre en Meta**, y la plantilla tiene
que cumplir cuatro cosas o el envío se rechaza:

1. Estar **APPROVED** (no PENDING).
2. Ser de la **WABA propia** (la que tiene el número con `client_id` null).
3. Tener **exactamente 4 variables**.
4. Categoría **UTILITY** — no es un requisito del código, pero MARKETING se
   aprueba más lento, cuesta más y puede quedar bloqueada por las preferencias
   de marketing del destinatario.

Cuerpo esperado, con las variables en este orden:

```
Ticket {{1}} — {{2}}. {{3}}. Por {{4}}.
```

| Variable | Contenido | Ejemplo |
|---|---|---|
| `{{1}}` | Ticket | `#482` |
| `{{2}}` | Título | `Banner de septiembre` |
| `{{3}}` | Qué cambió | `Pasó de Nuevos a En progreso` |
| `{{4}}` | Quién | `Luna Ávalos`, o `el sistema` sin sesión |

Los cuatro valores se limpian antes de salir: `NotifyTicketUpdate::limpiar()`
colapsa espacios, quita saltos de línea y trunca a 200 caracteres. **Meta
rechaza con 132000 cualquier parámetro con salto de línea o con cuatro espacios
seguidos**, y un título pegado desde un correo trae las dos cosas.

## Cuándo no llega, y dónde mirar

Ninguno de estos casos rompe el ticket. Todos quedan en el log.

| Síntoma | Causa | Dónde se ve |
|---|---|---|
| No se encola nada | Falta `TO` o `TEMPLATE` | En ningún sitio: es el apagado normal |
| El job corre y no manda | No hay número propio activo | `aviso de ticket: no hay número propio activo` |
| El job corre y no manda | El nombre de la plantilla no existe en esa WABA | `aviso de ticket: la plantilla configurada no existe` |
| El job corre y no manda | Plantilla PENDING, o no son 4 variables | `aviso de ticket: La plantilla «…» no está aprobada` |
| El mensaje sale `failed` en la bandeja | Meta rechazó el envío | El hilo de Conversaciones lo pinta en rojo, y el log de `WhatsAppService` trae el código |
| Nada se mueve | El worker de la cola no está corriendo | `jobs` se llena y no baja |

Un aviso que Meta rechaza **sí** queda guardado en el hilo, marcado como
fallido. Es deliberado: perder el envío es preferible a perder el registro de
que se intentó.

## Cómo probarlo de verdad

Los tests usan `Http::fake()`, y este repo ya aprendió lo que eso vale (§D de
`docs/whatsapp-multi-waba.md`). La única prueba que cuenta:

1. Plantilla creada y **APPROVED** en Plantillas WA.
2. Las dos variables de entorno puestas, y `config:clear`.
3. `php artisan queue:work` corriendo.
4. Mover un ticket de columna en el Kanban.
5. Que llegue el WhatsApp **y** que el mensaje aparezca en Conversaciones con
   `delivery_status = sent`.

Si llega el WhatsApp pero no aparece en la bandeja, o al revés, algo está mal
aunque parezca que funciona.

## Si mañana hay que ampliarlo

- **Más eventos** (prioridad, fecha de entrega): añadir el `wasChanged()` en
  `TicketObserver::updated()`. Pensar antes cuántos mensajes al día implica.
- **Varios destinatarios**: hoy `to` es uno solo. Convertirlo en lista obliga a
  decidir si todos reciben todo o cada quien lo suyo, que es una decisión de
  producto, no de código.
- **Avisar al cliente y no solo al equipo**: cambia el destinatario y el número
  emisor —saldría desde el número del cliente, no el de LunAvalos— y entonces
  la plantilla tiene que estar aprobada en **la WABA de ese cliente**, no en la
  nuestra. No es el mismo problema.
- **Disparar desde fuera** (n8n, klwebapp): eso sí pasa por la API de
  plataforma, y hoy **no funciona para el número propio**.
  `ApiController::clienteId()` devuelve el `client_id` del consumidor si está
  atado, y exige uno en el cuerpo si es interno — así que **nunca devuelve
  null**, y null es la única forma de alcanzar el número propio
  (`client_id = null`). El docblock dice que devolver null es "un caso
  legítimo", pero esa rama es inalcanzable. Es un bug, no una limitación de
  diseño, y hay que arreglarlo antes de intentarlo.
