# Cambios al sitio lunavalos.com — v3: todas las plataformas

> **Para el agente que trabaja en el sitio público** (no en `lunavalos-admin`).
> Autocontenido: no necesitas contexto previo.
> **v3 — 2026-08-13.** Sustituye a `sitio-web-cambios-whatsapp.md` (v2), que
> cubría solo WhatsApp. Este documento conserva lo que quedó pendiente de v2 y
> agrega lo que exigen las otras cinco plataformas.
> Auditado contra **producción** (`curl` a lunavalos.com), no contra un repo.

## Contexto

LunAvalos Digital House opera dos servicios que dependen de APIs de terceros:

1. **Atención a clientes por WhatsApp**, con la WhatsApp Business Platform de Meta.
2. **Gestión y publicación de contenido en redes sociales** por cuenta de sus
   clientes: Facebook Pages, Instagram Business, LinkedIn, TikTok y YouTube.

Los seis programas de revisión (Meta Access Verification, Meta App Review,
LinkedIn Marketing Developer Platform, TikTok Content Posting audit, Google
OAuth verification) abren el sitio público y buscan lo mismo: que **el servicio
esté descrito** y que **el tratamiento de datos esté declarado**. Google y TikTok
además exigen textos y archivos concretos que hoy no existen.

## Estado verificado en producción — 2026-08-13

Códigos de respuesta:

| Ruta | Estado |
|---|---|
| `/` | 200 |
| `/servicios` | 200 |
| `/privacy` | 200 |
| `/terms` | **307 → `/terminos`** (200) |
| `/aviso-de-privacidad` | 200 |
| `/eliminar-datos` | 200 |

**Aplicado y correcto (ronda 1) — no lo rehagas:**

- `/privacy` §2: tokens de las cinco redes + WhatsApp Business API, WABA IDs,
  phone number IDs, historial de actividad.
- `/privacy` §3 y §4: finalidades y lista de plataformas (Meta, WhatsApp,
  LinkedIn, TikTok, Google/YouTube).
- `/terminos` §1, §2, §3, §5: mensajería, cifrado de tokens, propiedad de la
  WABA, Embedded Signup, Business Verification del cliente, limitaciones.
- `/aviso-de-privacidad` §3 y §4: finalidades de mensajería y lista de APIs.
- `/eliminar-datos`: **la mejor página del sitio para estos trámites.** Cubre las
  seis plataformas, con rutas de revocación en cada una y plazo de 30 días
  hábiles. Es la que va como *Data Deletion Instructions URL* en Meta. No la
  toques salvo lo que pide C.6.

**No aplicado — la ronda 2 completa (Partes A y B de este documento) sigue
pendiente al 100%.** Verificado string por string sobre el HTML servido.

## Reglas para aplicar

- Respeta la estructura, componentes y diseño existentes de cada página.
- Respeta el idioma: `/privacy` en inglés; `/terminos`, `/aviso-de-privacidad`,
  `/servicios` y `/eliminar-datos` en español.
- Actualiza los índices de contenido ("Contents" / "Contenido") si agregas
  secciones.
- Actualiza las fechas de "Last updated / Última actualización". Hoy las cuatro
  legales dicen "Junio 2026".
- `hola@lunavalos.com` **no** es el correo oficial. Es `contacto@lunavalos.com`.
- No inventes certificaciones, cifras ni plazos que no estén aquí.

---

# PARTE A — Bloqueante (hacer primero)

Sin esto no se puede enviar el formulario a Meta.

## A.1 — `/servicios`: agregar la tarjeta de WhatsApp

La página tiene hoy 6 tarjetas (Tailormade Web Apps, Sitios web profesionales,
E-commerce & Headless Commerce, Inteligencia artificial aplicada, Email marketing
& automatización, Digitalización de negocios). **Ninguna describe el servicio de
WhatsApp.** Lo único que dice "WhatsApp" en esa página es el CTA "WhatsApp
directo" — eso no cuenta para Meta.

Agregar una séptima tarjeta con el mismo componente y formato que las demás.

**Título:**

```
Atención a clientes por WhatsApp
```

**Descripción:**

```
Conectamos la cuenta de WhatsApp Business de tu empresa a nuestra plataforma de
gestión. Cada mensaje que te envían tus clientes se convierte en un ticket con
seguimiento, tu equipo responde desde un solo panel y la respuesta llega al chat
del cliente. Incluye historial completo de conversaciones, asignación por
responsable y reportes de atención.
```

**Etiquetas / chips:**

```
WhatsApp Business Platform · Tickets · Historial de conversaciones · Reportes
```

**Sobre el enlace "Saber más":** las seis tarjetas actuales llevan a páginas de
detalle bajo `/servicios/{slug}`. Crea `/servicios/whatsapp-atencion-clientes`
reutilizando la plantilla existente, con el texto de arriba como contenido. Si
decides no crear la página, apunta a `/contacto`. Lo que **no** debe pasar es que
quede un enlace roto.

> Es un servicio para clientes con contrato, no de autoservicio. No agregues
> formulario de alta ni precios.

## A.2 — Unificar domicilios (etiquetarlos, no elegir uno)

Hoy las tres páginas legales muestran **solo el domicilio fiscal, sin etiqueta**
que lo identifique como tal, y el comercial aparece únicamente en el footer bajo
el encabezado genérico "Dirección". Son dos domicilios distintos y ambos son
correctos:

| Rol | Dirección |
|---|---|
| **Domicilio fiscal / legal** | Calle Gallo 118, Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, México |
| **Oficinas comerciales** | Av. La Salle #437, Col. La Salle, Saltillo, Coahuila, C.P. 25286, México |

Meta coteja el domicilio del sitio contra los documentos de Business
Verification, que usan el **fiscal (Gallo)**; por eso el fiscal debe estar
presente y **claramente identificado como tal** en las páginas legales.

### `/privacy` — sección 8 "Contact Information"

El encabezado actual es "Headquarters". Reemplazar el bloque por:

```
Registered Office (legal address)
LunAvalos Digital House, S.A.S.
Calle Gallo 118, Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, Mexico

Business Office
Av. La Salle #437, Col. La Salle, Saltillo, Coahuila, C.P. 25286, Mexico
```

### `/privacy` — sección 1 "Who We Are"

El bloque "Headquarters / Saltillo, Coahuila, Mexico" es correcto pero vago.
Cambiar la etiqueta a `Registered Office` y poner la dirección completa de Gallo.

### `/aviso-de-privacidad` — sección 1 "Responsable del Tratamiento"

El encabezado actual es "Domicilio Oficial" y solo dice "Saltillo, Coahuila,
México". Reemplazar el párrafo y el bloque por:

```
LunAvalos Digital House, S.A.S., con domicilio fiscal en Calle Gallo 118,
Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, México, es el responsable
del uso y protección de sus datos personales.

Domicilio fiscal
Calle Gallo 118, Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, México

Oficinas comerciales
Av. La Salle #437, Col. La Salle, Saltillo, Coahuila, C.P. 25286, México

Contacto
contacto@lunavalos.com
```

### `/terminos` — sección 8 "Contacto"

El encabezado actual es "Dirección Corporativa". Mismo tratamiento: etiquetar
como **Domicilio fiscal** el de Gallo y agregar **Oficinas comerciales** con el
de La Salle.

### Footer (componente global de todas las páginas)

Hoy el bloque dice "Dirección". Cambiar ese encabezado por:

```
Oficinas
Av. La Salle #437
Col. La Salle, Saltillo, Coahuila
México CP 25286
```

Con la etiqueta "Oficinas" el footer deja de contradecir a las legales.

## A.3 — Unificar el correo

**El correo oficial es `contacto@lunavalos.com`.**

Sustituir **todas** las apariciones de `hola@lunavalos.com`.

> Verificado hoy: la cadena está en el **archivo de mensajes / i18n**, bajo la
> clave `"email"` del bloque de contacto, y por eso viaja en el payload de
> **todas** las páginas del sitio y se renderiza en el bloque de contacto del
> home. Haz `grep -r "hola@lunavalos.com"` en todo el repo incluyendo los JSON de
> traducciones, y reemplaza en **todos los idiomas**, no solo en `es`.

---

# PARTE B — WhatsApp App Review (en paralelo, no bloquea)

Falta declarar la categoría de datos de **clientes finales** — las personas que
escriben por WhatsApp a nuestros clientes. Hoy no está en ninguna página.

Guardamos su teléfono, su nombre de perfil de WhatsApp y el contenido de sus
mensajes, y ninguna política lo declara. Verificado: las palabras "opt-in",
"encargado" y "ventana de 24 horas" no aparecen en ninguna página del sitio.

## B.1 — `/privacy` §2: bloque nuevo de datos de clientes finales

Agregar al final de la sección 2, con el mismo formato de tarjeta que los bloques
existentes (Contact Information, Access Credentials, Profiles & Pages, Activity
History):

```
End-Customer Data (Processed on Behalf of Clients)

When a client uses our WhatsApp support service, we receive and store the phone
number, WhatsApp profile name, and message content of the people who contact
that client. We process this data solely as a service provider, acting on the
client's instructions and for the sole purpose of delivering the support
service. The client remains the data controller for this information. We do not
use it for advertising, we do not sell it, and we never share it between
clients.
```

## B.2 — `/privacy` §5 "Data Retention": agregar al final

```
WhatsApp conversation records, including message content and end-customer
contact details, are retained for the duration of the contractual relationship
and deleted within 90 days of its termination. A client may request earlier
deletion of their conversation records at any time.
```

## B.3 — `/privacy` §6 "Your Rights": agregar antes del bloque "Delete Digital Data"

```
If you contacted one of our clients through WhatsApp and want to access or
delete your data, please direct your request to that business, which is the
controller of the conversation. If you contact us directly at
contacto@lunavalos.com we will forward your request to them and act on their
instructions.
```

## B.4 — `/aviso-de-privacidad` §2: apartado D nuevo

Agregar después del apartado C ("Datos Financieros y Patrimoniales"), con el
mismo formato:

```
D. Datos de Terceros (Clientes Finales de Nuestros Clientes)

Número telefónico, nombre de perfil de WhatsApp y contenido de los mensajes
enviados por las personas que contactan a nuestros clientes a través de sus
cuentas de WhatsApp Business.

Estos datos se tratan exclusivamente por cuenta y bajo instrucción del cliente
titular de la cuenta, quien conserva el carácter de responsable del tratamiento.
LunAvalos actúa únicamente como encargado. No se utilizan con fines
publicitarios, no se comercializan y no se comparten entre distintos clientes.
```

## B.5 — `/aviso-de-privacidad` §5 "Retención y Conservación": agregar al final

```
Los registros de conversaciones de WhatsApp, incluyendo el contenido de los
mensajes y los datos de contacto de los clientes finales, se conservan durante
la vigencia de la relación contractual y se eliminan dentro de los 90 días
posteriores a su terminación. El cliente titular de la cuenta puede solicitar su
eliminación anticipada en cualquier momento.
```

## B.6 — `/aviso-de-privacidad` §6 "Derechos ARCO": agregar al final

```
Titulares que no son clientes de LunAvalos

Si usted contactó a una empresa a través de WhatsApp y desea ejercer sus
derechos de Acceso, Rectificación, Cancelación u Oposición respecto de esa
conversación, deberá dirigir su solicitud a dicha empresa, que es la responsable
del tratamiento. Si nos contacta directamente en contacto@lunavalos.com,
canalizaremos su solicitud con la empresa correspondiente y actuaremos conforme
a sus instrucciones, en nuestro carácter de encargado.
```

## B.7 — `/terminos` §3: agregar el opt-in

La palabra "opt-in" no aparece hoy en ninguna página del sitio, y Meta la busca
explícitamente. Agregar a la lista de responsabilidades del cliente, con el mismo
formato (➔):

```
➔ Obtener el consentimiento (opt-in) de sus clientes finales antes de iniciar
  conversaciones por WhatsApp, y conservar evidencia del mismo.

➔ Reconocer que es el responsable del tratamiento de los datos personales de sus
  clientes finales, y que LunAvalos actúa como encargado por cuenta suya.
```

## B.8 — `/terminos` §5: agregar tarjeta de limitación

La sección tiene hoy cinco tarjetas (Cambios de Algoritmo, Restricciones de
Cuenta, Resultados Específicos, Revocación de Acceso, Fallas de Terceros).
Agregar una sexta con el mismo formato:

```
Entrega de Mensajes

La entrega de los mensajes de WhatsApp depende de las reglas de la plataforma de
Meta, incluida la ventana de 24 horas para mensajes de texto libre y la
aprobación previa de plantillas para mensajes fuera de dicha ventana. LunAvalos
no será responsable por mensajes no entregados por causas atribuibles a estas
reglas, a la calificación de calidad del número del cliente o a límites de
mensajería impuestos por Meta.
```

---

# PARTE C — Redes sociales (Facebook, Instagram, LinkedIn, TikTok, YouTube)

Nuevo en v3. El sistema ya pide estos permisos en producción, y el sitio no los
respalda. Estos son los scopes reales que solicita el OAuth del admin:

| Plataforma | Scopes solicitados hoy | Qué hace el sistema |
|---|---|---|
| **Facebook** | `pages_show_list`, `pages_read_engagement`, `pages_manage_posts` | Publica en el feed y sube fotos a las Pages del cliente |
| **Instagram** | `instagram_basic`, `instagram_content_publish`, `business_management` | Publica en cuentas IG Business vinculadas a esas Pages |
| **LinkedIn** | `w_member_social` (+ `w_organization_social` y `r_organization_admin` pendientes de aprobación) | Publica como miembro y, tras aprobación, en páginas de empresa |
| **TikTok** | `user.info.basic`, `video.publish`, `video.upload` | Sube y publica video (Direct Post) |
| **YouTube** | `youtube`, `youtube.upload` | Sube video al canal del cliente |

> Nota: los programas de revisión de cada plataforma cambian sus requisitos con
> frecuencia. Lo de esta parte es la forma de lo que piden; verifica los textos
> y URLs exactos contra la documentación vigente al momento de enviar cada
> solicitud.

## C.1 — `/servicios`: tarjeta de gestión de redes sociales

Hoy la publicación en redes **no existe como servicio** en `/servicios`. Solo
aparece como un renglón dentro de la descripción de "Digitalización de negocios"
("...gestión de redes sociales y analítica web avanzada..."). Eso no basta:
Meta, LinkedIn y TikTok abren el sitio a buscar el servicio descrito, y ese
renglón no dice qué hacemos ni con qué cuentas.

Agregar una octava tarjeta, mismo componente y formato:

**Título:**

```
Gestión y publicación en redes sociales
```

**Descripción:**

```
Administramos las cuentas de redes sociales de tu empresa desde un solo panel.
Tu equipo aprueba el contenido, nosotros lo programamos y lo publicamos en tus
páginas de Facebook, cuentas de Instagram Business, LinkedIn, TikTok y YouTube
en las fechas acordadas. La conexión se hace con la autorización expresa del
titular de cada cuenta y puede revocarse en cualquier momento.
```

**Etiquetas / chips:**

```
Facebook · Instagram · LinkedIn · TikTok · YouTube · Calendario de publicación
```

Mismo criterio que A.1 para "Saber más": crea `/servicios/redes-sociales` con la
plantilla existente, o apunta a `/contacto`. Sin enlaces rotos.

> Igual que WhatsApp: servicio para clientes con contrato. Sin formulario de
> alta ni precios.

## C.2 — `/privacy` §2 "Access Credentials": detallar qué autorizamos

El bloque actual dice que guardamos tokens de las cinco redes. Falta decir **qué
permiten esos tokens**, que es exactamente lo que el revisor coteja contra los
permisos solicitados. Reemplazar el texto del bloque por:

```
Access Credentials

Access tokens for the social media and messaging platforms our clients
authorize us to manage: Facebook Pages, Instagram Business accounts, LinkedIn
profiles and company pages, TikTok accounts, YouTube channels, and the WhatsApp
Business API (encrypted at rest).

These tokens grant us only the permissions required to deliver the contracted
service: to list the pages, accounts and channels a client administers, to
publish and schedule content on them, and to retrieve the resulting activity
metrics. We do not use them to read private messages on social networks, to
manage advertising, or to access data belonging to any account other than the
ones the client explicitly connects.
```

## C.3 — `/privacy`: sección nueva de YouTube API Services (obligatoria)

Los YouTube API Services Terms of Service exigen que la política de privacidad de
cualquier cliente API declare el uso del servicio y enlace a los términos de
YouTube y a la política de privacidad de Google. **Hoy no hay una sola mención**,
y sin ella la verificación OAuth de Google se rechaza — `youtube.upload` es un
scope restringido.

Agregar una sección nueva después de la 4 ("Third-Party Platforms"), renumerando
las siguientes y actualizando el índice "Contents":

```
5. YouTube API Services

Our YouTube publishing feature uses the YouTube API Services. By connecting a
YouTube channel to our platform, you agree to be bound by the YouTube Terms of
Service, available at https://www.youtube.com/t/terms.

Google's Privacy Policy, which governs Google's handling of the data accessed
through these APIs, is available at https://policies.google.com/privacy.

We access your YouTube channel solely to upload and publish the video content
you approve, and to retrieve public performance metrics for that content. We do
not read, download, or store any other data from your channel, and we do not
share YouTube data with third parties.

You can revoke our application's access to your Google account at any time from
https://myaccount.google.com/permissions. Upon revocation, we delete the stored
credentials for that channel; see our Data Deletion page for the full procedure.
```

Espejo en español en `/aviso-de-privacidad`, como sección nueva antes de
"Retención y Conservación":

```
Uso de los YouTube API Services

La función de publicación en YouTube utiliza los YouTube API Services. Al
vincular un canal de YouTube a nuestra plataforma, usted acepta los Términos de
Servicio de YouTube, disponibles en https://www.youtube.com/t/terms.

El Aviso de Privacidad de Google, que rige el tratamiento que Google hace de los
datos accesibles a través de estas APIs, está disponible en
https://policies.google.com/privacy.

Accedemos a su canal únicamente para subir y publicar el contenido de video que
usted aprueba y para consultar las métricas públicas de desempeño de dicho
contenido. No leemos, descargamos ni almacenamos ningún otro dato del canal, ni
compartimos datos de YouTube con terceros.

Puede revocar el acceso de nuestra aplicación a su cuenta de Google en cualquier
momento desde https://myaccount.google.com/permissions.
```

## C.4 — `/privacy` §6 "Your Rights": revocación por plataforma

Google exige que la vía de revocación esté **en la política de privacidad**, no
solo en una página aparte. `/eliminar-datos` ya tiene las rutas y está bien
hecha; lo que falta es que `/privacy` las enlace. Agregar al final de la sección,
junto al botón "Delete Digital Data":

```
Revoking platform access directly

You can revoke our application's access from each platform's own settings at any
time:

Meta (Facebook, Instagram, WhatsApp) — facebook.com/settings?tab=applications
LinkedIn — linkedin.com/psettings/permitted-services
TikTok — Settings and privacy > Security and permissions > Manage app permissions
Google (YouTube) — myaccount.google.com/permissions
```

Espejo en español en `/aviso-de-privacidad` §6, junto al botón "Eliminar Datos
Digitales". Los enlaces ya existen en `/eliminar-datos` §4: reutilízalos, no los
dupliques con textos distintos.

## C.5 — `/privacy` y `/aviso-de-privacidad`: declaración de uso limitado

Las Platform Terms de Meta y las políticas equivalentes de LinkedIn, TikTok y
Google exigen una declaración explícita de uso limitado. Hoy `/privacy` §4 dice
"solely for the purpose of content management", que se queda corto: no dice que
no vendemos, no cruzamos datos entre clientes ni entrenamos modelos.

Agregar al final de `/privacy` §4 "Third-Party Platforms":

```
Limited use of platform data

Data obtained through these platform APIs is used only to deliver the contracted
service to the client who authorized the connection. We do not sell it, we do
not use it for advertising or audience building, we do not use it to train
machine learning models, and we never combine or share the data of one client
with that of another. Each client's connected accounts are isolated from every
other client's.
```

Y en `/aviso-de-privacidad` §4, al final:

```
Uso limitado de los datos de plataforma

Los datos obtenidos a través de estas APIs se utilizan exclusivamente para
prestar el servicio contratado al cliente que autorizó la vinculación. No se
comercializan, no se utilizan con fines publicitarios ni de construcción de
audiencias, no se emplean para entrenar modelos de aprendizaje automático y no
se combinan ni comparten entre distintos clientes. Las cuentas vinculadas de
cada cliente permanecen aisladas de las de cualquier otro.
```

## C.6 — `/eliminar-datos`: agregar la revocación de Google

La página lista Meta, LinkedIn y TikTok con sus rutas. Verifica que **Google /
YouTube** esté en esa lista con la ruta `myaccount.google.com/permissions`; si
falta, agrégalo con el mismo formato de tarjeta que los demás.

Es el único cambio que necesita esta página. Todo lo demás en ella está bien.

## C.7 — `/terminos` §3: responsabilidades del cliente por plataforma

La lista actual habla solo de WhatsApp. Agregar, con el mismo formato (➔):

```
➔ Garantizar que es titular o administrador autorizado de las páginas, cuentas y
  canales que vincula (Facebook, Instagram, LinkedIn, TikTok y YouTube), y contar
  con la facultad de otorgar acceso a LunAvalos.

➔ Garantizar que posee los derechos de uso del contenido que aprueba para
  publicación, incluyendo imágenes, video y música, y responder por cualquier
  reclamación de terceros derivada de dicho contenido.

➔ Cumplir con los términos de servicio y las políticas de contenido de cada
  plataforma en la que solicita publicar.
```

## C.8 — Verificación de dominio: archivos estáticos en la raíz

Tres trámites requieren demostrar propiedad del dominio sirviendo un archivo o
una meta-etiqueta desde `https://lunavalos.com/`:

| Trámite | Método |
|---|---|
| **TikTok** (URL properties / Content Posting API) | archivo `.txt` con nombre asignado, en la raíz |
| **Google** (Search Console, prerrequisito de la verificación OAuth) | archivo HTML en la raíz, o registro DNS TXT |
| **Meta** (Domain Verification en el Business Manager) | meta-etiqueta en el `<head>` del home, archivo HTML en la raíz, o DNS TXT |

Qué hace falta del lado del sitio:

1. Que los archivos colocados en `public/` se sirvan efectivamente en la raíz del
   dominio, **sin prefijo de idioma**.
2. **Revisa el middleware de i18n.** Si el `matcher` de `src/middleware.ts` no
   excluye estas rutas, una petición a `/tiktokXXXXXXXX.txt` se reescribe a
   `/es/tiktokXXXXXXXX.txt` y el verificador recibe un 404 o un HTML en vez del
   texto plano. Es la causa más común de que estas verificaciones fallen en un
   Next.js con next-intl. Excluye del matcher los archivos con extensión y las
   rutas de verificación.
3. Si prefieres DNS TXT en Google y Meta, solo queda TikTok como archivo — pero
   el punto 2 sigue aplicando para ese.

Verifica cada uno con `curl -i https://lunavalos.com/<archivo>` y confirma
**200 + `content-type: text/plain`** (o `text/html` según el caso) **antes** de
apretar "Verificar" en el panel correspondiente.

## C.9 — URLs canónicas para los formularios

Al llenar los formularios de las cinco plataformas, usa las URLs que responden
**200 directo**, no las que redirigen:

| Campo del formulario | URL a declarar |
|---|---|
| Privacy Policy URL | `https://lunavalos.com/privacy` |
| Terms of Service URL | `https://lunavalos.com/terminos` ← **no** `/terms`, que responde 307 |
| Data Deletion Instructions URL | `https://lunavalos.com/eliminar-datos` |
| App / Website URL | `https://lunavalos.com/` |

Deja el redirect `/terms → /terminos` en su lugar (hay enlaces externos), pero no
lo declares: algunos validadores tratan el 307 como fallo.

---

# PARTE D — Fuera del sitio, pero bloquea igual

Estos dos puntos **no se arreglan en el repo del sitio**. Van al equipo de
`lunavalos-admin` y se listan aquí para que no se pierdan.

## D.1 — Cifrado de los tokens — ✅ resuelto en el admin (2026-08-14)

`/privacy` §2 dice que los tokens están *"encrypted at rest"* y `/terminos` §2
promete "el cifrado en reposo de las credenciales y tokens de acceso otorgados".
Hasta el 2026-08-14 eso era falso: `access_token` y `refresh_token` estaban en
`$hidden` pero sin cast, en claro en la base.

Aplicado en `lunavalos-admin`:

- Cast `encrypted` en `access_token` y `refresh_token` (`app/Models/SocialAccount.php`).
- Migración `2026_08_14_000000_encrypt_social_account_tokens` que cifra las filas
  existentes; idempotente y reversible.
- **Fuga adicional encontrada de paso:** el `page_token` de Facebook e Instagram
  se guardaba duplicado dentro del JSON `meta`, que no está en `$hidden` y viaja
  al navegador en el payload de Inertia de `/social/clients/{client}`. Se eliminó
  de `meta` y los publishers ahora leen `access_token`. La migración lo borra de
  las filas viejas.
- `tests/Feature/SocialAccountTokenEncryptionTest.php` cubre el cifrado, la
  serialización y el backfill.

**Las legales del sitio ya dicen la verdad. No las suavices.**

## D.2 — Métricas declaradas, sin permisos ni implementación

`/privacy` §3 punto 4 y `/aviso-de-privacidad` §3 punto 4 prometen "reportes de
métricas y actividad". En el admin, `fetchInsights()` y `fetchAccountStats()`
devuelven arreglo vacío en `AbstractPublisher`: son stubs.

Cuando se implementen harán falta scopes que **hoy no se solicitan**
(`read_insights` para Pages, `instagram_manage_insights` para IG) y que hay que
declarar en el mismo App Review. Si el review se envía antes, habrá que abrir
otro. Vale la pena decidirlo ahora.

---

# PARTE E — La app vive en otro dominio (`admin.lunavalos.com`)

Las políticas y la descripción del servicio van en `lunavalos.com`, que es lo
correcto: los revisores verifican **al negocio**. Pero hay un segundo grupo de
URLs que apunta a donde corre la app, y ahí es donde se cometen los errores.

## E.0 — Estado de la app de Meta al 2026-08-14

Auditado y configurado en el panel:

| Punto | Estado |
|---|---|
| Business verification | ✅ Verified |
| **Access verification (Tech Provider)** | ✅ **Verified** — se acabó el plazo del 10/12/2026 |
| Basic settings (dominios, URLs, dirección fiscal, ícono, categoría) | ✅ Completo |
| Callback URL del webhook | ✅ `https://admin.lunavalos.com/whatsapp/webhook` |
| App Review | ❌ **Not submitted** (cola ya depurada, ver abajo) |
| Publish | ❌ Unpublished — mientras tanto Meta **solo entrega webhooks de prueba** |

Cola de App Review, alineada con `SocialAuthController::scopesFor()`:

```
whatsapp_business_messaging, whatsapp_business_management,
instagram_basic, instagram_content_publish,
pages_manage_posts, pages_show_list, pages_read_engagement,
business_management, public_profile
```

> **Instagram va por Facebook Login.** Meta ofrece dos familias y no son
> intercambiables: `instagram_business_*` pertenece a *API setup with Instagram
> login* (otro flujo de OAuth) e `instagram_basic` / `instagram_content_publish`
> a *API setup with Facebook login*, que es la que usa nuestro código. La app
> estaba pidiendo la primera. Ya se corrigió.

Falta en el panel: llenar *Provide testing instructions* (ver E.3) y enviar el
App Review.

## E.1 — URLs a registrar en los paneles

| Campo | Valor |
|---|---|
| App Domains (Meta) | `admin.lunavalos.com` |
| Site URL (Facebook Login) | `https://admin.lunavalos.com/` |
| Valid OAuth Redirect URIs | `https://admin.lunavalos.com/social/oauth/{provider}/callback` — ver `routes/web.php:180` |
| WhatsApp Callback URL | `https://admin.lunavalos.com/whatsapp/webhook` |
| Privacy Policy URL | `https://lunavalos.com/privacy` |
| Terms of Service URL | `https://lunavalos.com/terminos` |
| Data Deletion Instructions URL | `https://lunavalos.com/eliminar-datos` |

## E.2 — Enlaces legales en el login — ✅ resuelto (2026-08-14)

`https://admin.lunavalos.com/` redirige a `/login`, y esa página no tenía una
sola mención de privacidad ni términos. Un revisor que abría el dominio de la
app veía una caja de login sin ninguna conexión con las políticas declaradas.
Para Google es requisito duro: la pantalla de consentimiento OAuth exige una
homepage que enlace la política de privacidad.

Agregado en `resources/js/Layouts/GuestLayout.vue`: pie con enlaces a
`/privacy`, `/terminos`, `/eliminar-datos` y al sitio corporativo.

## E.3 — Usuario de prueba para App Review — pendiente, requiere decisión

No hay registro público (`routes/auth.php`). Meta, y en su caso LinkedIn y
TikTok, exigen credenciales con las que el revisor reproduzca el flujo completo,
o un screencast que lo cubra de principio a fin.

Hace falta decidir credenciales y qué cliente de ejemplo verá el revisor, y
crear la cuenta con datos ficticios — nunca con un cliente real, porque el
revisor vería conversaciones de terceros.

## E.4 — Verificación de dominio de TikTok — pendiente, requiere el archivo

El redirect URI vive en `admin.lunavalos.com`, así que la propiedad a verificar
es ese subdominio y el archivo `.txt` que asigna TikTok va en el `public/` de
**este** repo Laravel, no en el sitio Next.js. Para Google, una propiedad de
tipo Dominio en Search Console cubre los dos subdominios de una vez.

## E.5 — `APP_URL` en producción

`config/services.php` arma cada `redirect` concatenando `env('APP_URL')`. Si en
el servidor no vale exactamente `https://admin.lunavalos.com`, los redirect_uri
generados no coinciden con los registrados y todos los OAuth fallan con
`redirect_uri_mismatch`. Confírmalo antes de enviar cualquier solicitud.

---

# Criterios de aceptación

**Parte A (bloqueante):**

- [ ] `/servicios` tiene una tarjeta que describe la atención por WhatsApp, y su
      enlace "Saber más" no queda roto.
- [ ] `/privacy`, `/terminos` y `/aviso-de-privacidad` muestran ambos domicilios
      **etiquetados** como fiscal y comercial.
- [ ] El footer etiqueta su dirección como "Oficinas".
- [ ] `grep -r "hola@lunavalos.com"` no devuelve resultados en todo el repo,
      incluidos los JSON de i18n de todos los idiomas.

**Parte B (WhatsApp App Review):**

- [ ] `/privacy` declara los datos de clientes finales como categoría propia, con
      el encuadre responsable/encargado.
- [ ] `/privacy` §5 y §6 cubren retención y borrado de conversaciones.
- [ ] `/aviso-de-privacidad` tiene el apartado D y la vía ARCO para terceros.
- [ ] `/terminos` §3 incluye el opt-in; §5 incluye la limitación por entrega.

**Parte C (redes sociales):**

- [ ] `/servicios` tiene una tarjeta que describe la gestión y publicación en
      redes, con las cinco plataformas nombradas, y sin enlace roto.
- [ ] `/privacy` §2 explica qué permiten los tokens y qué no hacemos con ellos.
- [ ] `/privacy` y `/aviso-de-privacidad` tienen la sección de YouTube API
      Services con los tres enlaces (ToS de YouTube, privacidad de Google,
      revocación en la cuenta de Google).
- [ ] Ambas políticas incluyen la declaración de uso limitado.
- [ ] Ambas políticas enlazan la revocación por plataforma.
- [ ] `/eliminar-datos` incluye Google / YouTube en la lista de revocación.
- [ ] `/terminos` §3 cubre titularidad de cuentas y derechos del contenido.
- [ ] Los archivos de verificación de dominio responden 200 con el
      `content-type` correcto, sin reescritura del middleware de i18n.

**Todas:**

- [ ] Índices de contenido actualizados con las secciones nuevas y renumeradas.
- [ ] Fechas de última actualización al día en las cuatro páginas legales
      (`/privacy`, `/terminos`, `/aviso-de-privacidad`, `/eliminar-datos`).
- [ ] Las seis rutas siguen respondiendo como se espera: `/`, `/servicios`,
      `/privacy`, `/terminos`, `/aviso-de-privacidad`, `/eliminar-datos` en 200.
