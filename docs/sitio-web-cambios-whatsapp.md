# Cambios al sitio lunavalos.com — WhatsApp (v2, ronda 2)

> ⚠️ **Obsoleto al 2026-08-13. Usa `sitio-web-cambios-plataformas.md` (v3).**
> Nada de este documento se aplicó; v3 lo conserva íntegro en sus Partes A y B,
> con el estado real de producción verificado, y agrega las otras cinco
> plataformas.

> **Para el agente que trabaja en el repo del sitio web** (no en `lunavalos-admin`).
> Autocontenido: no necesitas contexto previo.
> **v2 — 2026-08-13.** La ronda 1 ya se aplicó parcialmente. Este documento marca
> qué quedó hecho (no lo repitas) y qué falta.

## Contexto

LunAvalos Digital House va a operar **atención a clientes por WhatsApp** para sus
clientes, con la WhatsApp Business Platform de Meta. Meta está revisando dos
trámites (*Access Verification / Tech Provider* y después *App Review*) y en
ambos abre el sitio público para verificar dos cosas: que **el servicio esté
descrito** y que **el tratamiento de datos esté declarado**.

## Estado verificado hoy en producción

Ya aplicado en la ronda 1 — **no lo rehagas**:

- `/privacy` §2: tokens de WhatsApp Business API *(crypted at rest)*, WABA IDs,
  phone number IDs, historial de mensajes.
- `/privacy` §3: puntos 2 y 3 sobre mensajería con end-users.
- `/privacy` §4: WhatsApp (Meta Platforms, Inc.) agregado a la lista de APIs.
- `/terms` §1: mensajería y WhatsApp en la descripción del servicio.
- `/terms` §2: cifrado en reposo de tokens, desvinculación de suscripciones.
- `/terms` §3: propiedad de la WABA, Embedded Signup, Business Verification
  propia del cliente, cumplimiento de políticas de Meta.
- `/terms` §5: desconexión de tokens y caídas de Meta/WhatsApp.
- `/aviso-de-privacidad` §3 y §4: finalidad de mensajería y WhatsApp en APIs.

Las cuatro rutas (`/`, `/servicios`, `/privacy`, `/terms`,
`/aviso-de-privacidad`) responden 200.

## Reglas para aplicar

- Respeta la estructura, componentes y diseño existentes de cada página.
- Respeta el idioma: `/privacy` en inglés; `/terms`, `/aviso-de-privacidad` y
  `/servicios` en español.
- Actualiza los índices de contenido si agregas secciones.
- Actualiza las fechas de "Last updated / Última actualización".
- No inventes certificaciones, cifras ni plazos que no estén aquí.

---

# PARTE A — Bloqueante (hacer primero)

Sin esto no se puede enviar el formulario a Meta.

## A.1 — `/servicios`: agregar la tarjeta del servicio

La página tiene hoy 6 tarjetas (Tailormade Web Apps, Sitios web profesionales,
E-commerce & Headless Commerce, Inteligencia artificial aplicada, Email marketing
& automatización, Digitalización de negocios). **Ninguna describe el servicio de
WhatsApp.** Lo único que dice "WhatsApp" en esa página es el CTA "WhatsApp
directo" y el botón flotante de contacto — eso no cuenta para Meta.

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

**Etiquetas / chips** (mismo estilo que las otras tarjetas):

```
WhatsApp Business Platform · Tickets · Historial de conversaciones · Reportes
```

**Sobre el enlace "Saber más":** las tarjetas actuales lo llevan. Si apunta a una
página de detalle por servicio, crea la correspondiente reutilizando la plantilla
existente y usando el texto de arriba como contenido. Si no quieres crear página
nueva, apunta el enlace a `/contacto`. Lo que **no** debe pasar es que quede un
enlace roto.

> Es un servicio para clientes con contrato, no de autoservicio. No agregues
> formulario de alta ni precios.

## A.2 — Unificar domicilios (etiquetarlos, no elegir uno)

Hoy las tres páginas legales muestran **dos direcciones sin etiqueta**, lo que
parece una contradicción. En realidad son dos domicilios distintos y ambos son
correctos:

| Rol | Dirección |
|---|---|
| **Domicilio fiscal / legal** | Calle Gallo 118, Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, México |
| **Oficinas comerciales** | Av. La Salle #437, Col. La Salle, Saltillo, Coahuila, C.P. 25286, México |

La solución es **etiquetar cada uno según su rol** en todos los lugares donde
aparezcan. Meta coteja el domicilio del sitio contra los documentos de Business
Verification, que usan el **fiscal (Gallo)**; por eso el fiscal debe estar
presente y claramente identificado en las páginas legales.

### `/privacy` — sección 8 "Contact Information"

Reemplazar el bloque de dirección por:

```
Registered Office (legal address)
LunAvalos Digital House, S.A.S.
Calle Gallo 118, Col. Las Maravillas, Saltillo, Coahuila, C.P. 25019, Mexico

Business Office
Av. La Salle #437, Col. La Salle, Saltillo, Coahuila, C.P. 25286, Mexico
```

### `/aviso-de-privacidad` — sección 1 "Responsable del Tratamiento"

Reemplazar el párrafo y el bloque de domicilio por:

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

### `/terms` — sección 8 "Contacto"

Mismo tratamiento: etiquetar como **Domicilio fiscal** el de Gallo y como
**Oficinas comerciales** el de La Salle.

### Footer (componente global de todas las páginas)

Hoy el bloque dice solo "Dirección". Cambiar ese encabezado por:

```
Oficinas
Av. La Salle #437
Col. La Salle, Saltillo, Coahuila
México CP 25286
```

Con la etiqueta "Oficinas" el footer deja de contradecir a las legales.

## A.3 — Unificar el correo

**El correo oficial es `contacto@lunavalos.com`.**

Sustituir **todas** las apariciones de `hola@lunavalos.com` por
`contacto@lunavalos.com` en todo el sitio.

> Ojo: la cadena `hola@lunavalos.com` no está solo en componentes. Aparece
> también en el archivo de traducciones / i18n (se ve en el payload de Next bajo
> la clave `"email"` del bloque de contacto). Haz un
> `grep -r "hola@lunavalos.com"` en todo el repo, incluyendo archivos de
> mensajes/JSON, y reemplaza en todos.

---

# PARTE B — Para App Review (en paralelo, no bloquea)

Falta declarar la categoría de datos de **clientes finales** — las personas que
escriben por WhatsApp a nuestros clientes. Hoy no está en ninguna página.

Es lo más importante que quedó pendiente: guardamos su teléfono, su nombre de
perfil de WhatsApp y el contenido de sus mensajes, y ninguna política lo declara.

## B.1 — `/privacy` §2: bloque nuevo de datos de clientes finales

Agregar al final de la sección 2, con el mismo formato de tarjeta que los
bloques existentes:

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

## B.7 — `/terms` §3: agregar el opt-in

La palabra "opt-in" no aparece hoy en ninguna página del sitio, y Meta la busca
explícitamente. Agregar a la lista de responsabilidades del cliente, con el mismo
formato (➔):

```
➔ Obtener el consentimiento (opt-in) de sus clientes finales antes de iniciar
  conversaciones por WhatsApp, y conservar evidencia del mismo.

➔ Reconocer que es el responsable del tratamiento de los datos personales de sus
  clientes finales, y que LunAvalos actúa como encargado por cuenta suya.
```

## B.8 — `/terms` §5: agregar tarjeta de limitación

Con el mismo formato que las tarjetas existentes de esa sección:

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

# Criterios de aceptación

**Parte A (bloqueante):**

- [ ] `/servicios` tiene una tarjeta que describe la atención por WhatsApp, y su
      enlace "Saber más" no queda roto.
- [ ] `/privacy`, `/terms` y `/aviso-de-privacidad` muestran ambos domicilios
      **etiquetados** como fiscal y comercial.
- [ ] El footer etiqueta su dirección como "Oficinas".
- [ ] `grep -r "hola@lunavalos.com"` no devuelve resultados en todo el repo.

**Parte B (App Review):**

- [ ] `/privacy` declara los datos de clientes finales como categoría propia, con
      el encuadre responsable/encargado.
- [ ] `/privacy` §5 y §6 cubren retención y borrado de conversaciones.
- [ ] `/aviso-de-privacidad` tiene el apartado D y la vía ARCO para terceros.
- [ ] `/terms` §3 incluye el opt-in; §5 incluye la limitación por entrega.

**Ambas:**

- [ ] Índices de contenido actualizados con las secciones nuevas.
- [ ] Fechas de última actualización al día.
- [ ] Las cinco rutas siguen respondiendo 200.
