# Avisos de ticket por WhatsApp

> Escrito el 2026-09-14, cuando se implementó.
> Para qué sirve este documento: dentro de seis meses, cuando el aviso deje de
> llegar, esto dice dónde mirar y por qué está hecho así.

## En una frase

Cuando **una persona** cambia el **estado** o el **responsable** de un ticket,
le sale un WhatsApp **al responsable del ticket** —nunca a quien hizo el
cambio— desde el número de LunAvalos, usando una **plantilla aprobada**. El
aviso queda registrado en el módulo de Conversaciones como cualquier otro
mensaje.

Las tres condiciones de esa frase son el diseño entero:

| Condición | Por qué |
|---|---|
| **Una persona** | Sin sesión el cambio lo hizo el scheduler. No tiene dueño ni urgencia, y cada aviso se factura |
| **Al responsable** | Es quien no estaba mirando la pantalla. El aviso existe para él |
| **Nunca a quien lo hizo** | Si arrastras una tarjeta tú misma, ya sabes que la arrastraste |

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
| `users.whatsapp` | A dónde se manda. Sin esto no llega nada |
| `app/Support/TelefonoWhatsApp.php` | Convierte lo que teclea una persona en un `wa_id` |
| `tests/Feature/TicketWhatsAppAlertTest.php` | Lo que se espera de todo esto |

El observer se registra en `AppServiceProvider::boot()`.

## El flujo, paso a paso

```
Alguien mueve una tarjeta del Kanban
  └─ POST /tickets/{id}/status  (o /assign, /service, /cycle, /start-work, PUT /tickets/{id})
       └─ $ticket->save()
            └─ TicketObserver::updated()
                 ├─ ¿está encendido? (si no, termina aquí y no encola nada)
                 ├─ ¿hay sesión? (si no, es el scheduler: termina)
                 ├─ ¿cambió `status` o `assigned_id`? (si no, termina)
                 ├─ ¿hay responsable, y es distinto de quien lo movió? (si no, termina)
                 └─ NotifyTicketUpdate::dispatch()  ──► cola `database`
                                                          │
  El request ya respondió. El ticket está guardado.       │
                                                          ▼
                                            NotifyTicketUpdate::handle()
                                              ├─ destino: users.whatsapp del responsable
                                              ├─ ¿por debajo del tope horario?
                                              ├─ número propio  (el declarado en config)
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

**El destinatario es el responsable, no un número fijo.** La primera versión
mandaba todo a `WHATSAPP_TICKET_ALERT_TO`. No servía: el aviso tiene que
llegarle a quien le acaban de asignar algo, y eso cambia con cada ticket. Ese
ajuste ya no existe; el número sale de `users.whatsapp`.

**Nunca se avisa a quien hizo el cambio.** Es la regla que más mensajes ahorra
—la mayoría de los movimientos los hace quien ya está mirando el tablero— y la
que hace que el aviso signifique algo: si te llega, es porque alguien más tocó
algo tuyo.

**Sin sesión no se avisa.** Ahí está la ráfaga de las 6 de la tarde, que merece
su propio apartado más abajo.

**Hay un tope de 10 avisos por número y hora.** Una acción masiva en el Kanban
dispararía una plantilla por ticket al mismo destinatario. Meta lo lee como
ráfaga, y el `quality_rating` que se quema es el de la WABA entera — o sea el
de todos los clientes, no solo el de quien provocó la ráfaga.

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

**El número emisor sale de configuración, no de la tabla.** Esto costó una
tarde, así que conviene dejarlo escrito.

`client_id = null` significa "número nuestro", pero **no identifica a uno**:
producción arrastra la WABA de prueba que Meta regala —registrada el
2026-08-19, nunca borrada (§10 del plan)—, y su número también tiene
`client_id` null, con un id más bajo que el real. La primera versión de
`NotifyTicketUpdate::numeroPropio()` elegía con `orderBy('id')->first()` y se
llevaba el de prueba.

El síntoma fue engañoso: el log decía **`la plantilla configurada no existe`**
mientras `ticket_actualizado` estaba aprobada y sincronizada. Y era verdad —
no existía *en esa* WABA, porque la plantilla vive en la real. El mensaje
culpaba a la plantilla de un error de selección de número.

Ahora manda `WHATSAPP_PHONE_NUMBER_ID`, con `WHATSAPP_BUSINESS_ACCOUNT_ID` de
respaldo, y **no hay fallback a "cualquier número propio"**: sin coincidencia no
se manda. Mandar desde la identidad equivocada es peor que no mandar, y encima
es invisible — el número de prueba solo entrega a 5 destinatarios dados de alta
a mano, así que el aviso se evapora sin un solo error.

Lo cubren `test_ignora_la_waba_de_prueba_y_usa_la_declarada_en_configuracion` y
`test_sin_numero_declarado_no_cae_a_cualquier_numero_propio`, ambos verificados
contra el código viejo: fallan con él.

## Configuración

```env
# El interruptor. Sin esto no se avisa nada.
WHATSAPP_TICKET_ALERT_TEMPLATE=ticket_actualizado

# Y las que dicen desde qué número sale el aviso.
WHATSAPP_PHONE_NUMBER_ID=1230737580126123
WHATSAPP_BUSINESS_ACCOUNT_ID=2436841820155807
```

> **`WHATSAPP_TICKET_ALERT_TO` ya no existe.** Estuvo en el diseño original y se
> quitó el 2026-09-16: el destinatario dejó de ser un número fijo. Si sigue en
> algún `.env`, no hace nada.

### El número de cada usuario

El aviso va a `users.whatsapp` del responsable del ticket. Se edita en dos
sitios, y **los dos guardan el valor ya normalizado**:

- **Mi perfil** → campo WhatsApp. Cada quien pone el suyo.
- **Usuarios → editar** → para cargárselo al equipo y a los usuarios de
  cliente.

`App\Support\TelefonoWhatsApp::normalizar()` convierte lo que se teclea en un
`wa_id`: quita todo lo que no sea dígito y, si quedan 10, le antepone `52`. Un
valor que no puede ser un teléfono se guarda como **null** — el campo vuelve
vacío al formulario, que es la señal de que no se aceptó.

Va en un mutator del modelo y no en los controladores porque hay tres sitios
que escriben este campo, y basta con que uno se salte la normalización para que
el aviso se mande a un destinatario inexistente **sin dar error**: Meta acepta
el envío, se registra en el hilo, y nadie lo recibe.

### La plantilla

`WHATSAPP_TICKET_ALERT_TEMPLATE` es el nombre en Meta, y la plantilla tiene que
cumplir cuatro cosas o el envío se rechaza:

1. Estar **APPROVED** (no PENDING).
2. Ser de la **WABA propia** (la que declara `WHATSAPP_BUSINESS_ACCOUNT_ID`).
3. Tener **exactamente 4 variables**.
4. Categoría **UTILITY** — no es requisito del código, pero MARKETING se
   aprueba más lento, cuesta más y puede quedar bloqueada por las preferencias
   de marketing del destinatario.

Cuerpo de la plantilla creada el 2026-09-14:

```
Hola, hubo un cambio en el ticket {{1}} — {{2}}. {{3}}. Actualizado por {{4}} en el panel de LunAvalos.
```

> El texto da igual mientras las 4 variables vayan en ese orden, pero **no
> puede ser mucho más corto**. Meta rechaza el cuerpo con *"This template has
> too many variables for its length"*: la primera versión —`Ticket {{1}} —
> {{2}}. {{3}}. Por {{4}}.`, 63 caracteres— no pasó la validación, y a 103
> caracteres sí. La regla no está documentada con un número exacto; con 4
> variables, cuenta con unos 100 caracteres.

| Variable | Contenido | Ejemplo |
|---|---|---|
| `{{1}}` | Ticket | `#482` |
| `{{2}}` | Título | `Banner de septiembre` |
| `{{3}}` | Qué cambió | `Te lo asignaron`, `Pasó de Nuevos a En progreso` |
| `{{4}}` | Quién lo hizo | `Luna Ávalos` |

Los cuatro valores se limpian antes de salir: `NotifyTicketUpdate::limpiar()`
colapsa espacios, quita saltos de línea y trunca a 200 caracteres. **Meta
rechaza con 132000 cualquier parámetro con salto de línea o con cuatro espacios
seguidos**, y un título pegado desde un correo trae las dos cosas.

## La ráfaga de las 6 de la tarde

Merece su propio apartado porque va a volver a morder, y no por este módulo.

`routes/console.php:13` programa `tickets:auto-close-in-review` con `->daily()`.
`config/app.php:68` tiene `'timezone' => 'UTC'`. Y `->daily()` significa
medianoche **en la zona de la app**, o sea las **18:00 en Saltillo**. Todos los
`->daily()` de este repo corren a las 6 de la tarde sin que nadie lo decidiera.

El comando cierra en un bucle todos los tickets con 5 días en «En Revisión»,
con un `save()` por ticket. Cada `save()` es un `updated`, y antes del gate eso
era un WhatsApp por ticket, todos a la misma hora.

El comando hermano, `auto-archive-completed`, **no** dispara nada, y por una
razón que conviene tener presente: usa `Ticket::where(...)->update([...])` sobre
el query builder, y eso **se salta los eventos de Eloquent** por completo. Si
alguien algún día lo "mejora" pasándolo a modelos, aparecería un pico nuevo sin
que nadie entienda de dónde sale.

## Cuándo no llega, y dónde mirar

Ninguno de estos casos rompe el ticket. Todos quedan en el log.

| Síntoma | Causa | Dónde se ve |
|---|---|---|
| No se encola nada | Falta `WHATSAPP_TICKET_ALERT_TEMPLATE` | En ningún sitio: es el apagado normal |
| No se encola nada | El cambio lo hizo el scheduler, o el responsable es quien lo movió, o el ticket no tiene responsable | En ningún sitio: es el comportamiento correcto |
| El job corre y no manda | El responsable no tiene número cargado | `aviso de ticket: el destinatario no tiene WhatsApp`, con su nombre |
| El job corre y no manda | Se pasó del tope de 10 por hora | `aviso de ticket: destinatario por encima del tope horario` |
| El job corre y no manda | No hay número propio activo, o `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_BUSINESS_ACCOUNT_ID` no están en el `.env` | `aviso de ticket: no hay número propio activo`, que ahora dice contra qué buscó |
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
