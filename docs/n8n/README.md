# WhatsApp vía n8n

Este sistema no habla con `graph.facebook.com`. El token de Meta vive solo en n8n.

```
Salida:   Laravel ──X-N8n-Secret──► n8n ──token Meta──► Graph API
Entrada:  Meta ──X-Hub-Signature-256──► n8n ──X-N8n-Secret──► Laravel
```

## Datos de la cuenta

| Dato | Valor |
|---|---|
| WABA ID | `2436841820155807` |
| Phone Number ID | `1230737580126123` |
| Número | +52 1 844 341 0326 |

## Variables de entorno en n8n

Se leen con `$env.` desde los nodos Code, así que hay que declararlas en el
entorno del contenedor de n8n (no en la UI):

| Variable | Qué es |
|---|---|
| `LUNAVALOS_SHARED_SECRET` | Mismo valor que `N8N_SHARED_SECRET` del `.env` de Laravel |
| `META_VERIFY_TOKEN` | Valor inventado; se pega igual en el panel de Meta |
| `META_APP_SECRET` | App Secret de la app de Meta (App Settings → Basic) |

Si n8n corre en Docker, `N8N_BLOCK_ENV_ACCESS_IN_NODE=false` es necesario para
que los nodos Code puedan leer `$env`.

## Credencial del token de Meta

Los dos nodos HTTP de `01-salida-whatsapp.json` usan **Header Auth**:

- Name: `Authorization`
- Value: `Bearer <token permanente del system user>`

Ese token es el del system user `whatsapp-api`, con `whatsapp_business_messaging`
y `whatsapp_business_management`, expiración **Never**.

## Orden de conexión

1. Importar los dos workflows y activarlos.
2. Copiar la URL productiva del webhook de entrada (`/webhook/meta-whatsapp`).
3. En Meta → Use cases → Connect on WhatsApp → Step 2, cambiar **Callback URL**
   a esa URL y **Verify token** al valor de `META_VERIFY_TOKEN`.
4. Verify and save. Confirmar que el campo `messages` siga en *Subscribed*.
5. En el `.env` de Laravel, poner `N8N_WHATSAPP_WEBHOOK_URL` apuntando a la URL
   productiva del webhook de salida (`/webhook/lunavalos-admin-whatsapp`).

No hacer el paso 3 antes de tener el workflow de entrada **activo**: si el
handshake falla, Meta cancela la suscripción.

## Añadir otro cliente

Cada sistema tiene su propio webhook de salida en n8n. Para dar de alta uno:

1. Duplicar `01-salida-whatsapp.json` con otro `path`.
2. Cambiar el Phone Number ID en la URL de los dos nodos HTTP.
3. En el workflow de entrada, enrutar por
   `entry[0].changes[0].value.metadata.phone_number_id` hacia el sistema que toque.

Business verification ya está aprobada, así que la WABA admite hasta 20 números.
