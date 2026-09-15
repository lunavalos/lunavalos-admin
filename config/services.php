<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp — Cloud API (entrada y salida, Meta directo)
    |--------------------------------------------------------------------------
    | Entrada: Meta llama a {APP_URL}/whatsapp/webhook
    |   GET  -> handshake, se compara hub.verify_token contra verify_token.
    |   POST -> eventos, se valida X-Hub-Signature-256 con app_secret.
    |
    | Salida: este sistema llama a graph.facebook.com/{graph_version}/
    |   {phone_number_id}/messages con el token como Bearer.
    |
    | verify_token lo elegimos nosotros y se copia tal cual en el panel de Meta.
    | app_secret es el App Secret de la app (Settings > Basic).
    | token es el del system user, con expiración Never.
    |
    | phone_number_id y token son el número por defecto. Cuando exista el
    | esquema multi-WABA, cada cliente traerá los suyos y estos quedarán solo
    | como respaldo del número propio de LunAvalos.
    */
    'whatsapp' => [
        'app_id'              => env('WHATSAPP_APP_ID'),
        'app_secret'          => env('WHATSAPP_APP_SECRET'),
        'verify_token'        => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v26.0'),
        'token'               => env('WHATSAPP_TOKEN'),
        'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'timeout'             => env('WHATSAPP_TIMEOUT', 10),

        // Embedded Signup: el `configuration_id` del flujo que se crea en el
        // panel de Meta. Sin él el SDK no puede lanzar el diálogo.
        'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),

        /*
        | Aviso interno por WhatsApp cuando cambia un ticket.
        |
        | Apagado mientras falte cualquiera de los dos valores: sin destino no
        | hay a quién avisar, y sin plantilla aprobada Meta rechaza el envío
        | con 131047 en cuanto se cierra la ventana de 24 h. Un aviso interno
        | casi siempre nace fuera de la ventana, así que la plantilla no es
        | opcional.
        |
        | `to` va en formato internacional y solo dígitos: 528442751165, no
        | 8442751165 ni +52 844 275 11 65.
        |
        | `template` es el NOMBRE de la plantilla en Meta, no su id: el id
        | cambia si se recrea la plantilla y dejaría el aviso muerto en
        | silencio. Tiene que estar APPROVED, ser de la WABA propia y llevar
        | exactamente 4 variables, en este orden:
        |
        |   {{1}} ticket   {{2}} título   {{3}} qué cambió   {{4}} quién
        |
        | La plantilla creada el 2026-09-14 es `ticket_actualizado`, con este
        | cuerpo:
        |
        |   Hola, hubo un cambio en el ticket {{1}} — {{2}}. {{3}}.
        |   Actualizado por {{4}} en el panel de LunAvalos.
        |
        | El texto da igual mientras las 4 variables vayan en ese orden, pero
        | no puede ser mucho más corto: Meta rechaza un cuerpo con demasiadas
        | variables para su longitud —63 caracteres con 4 variables no pasa,
        | 103 sí—.
        */
        'ticket_alerts' => [
            'to'       => env('WHATSAPP_TICKET_ALERT_TO'),
            'template' => env('WHATSAPP_TICKET_ALERT_TEMPLATE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic — agente de IA de las conversaciones
    |--------------------------------------------------------------------------
    | Modelo de cobro: una sola cuenta de LunAvalos, con tope por cliente en
    | `ai_agents.monthly_token_limit`. Un cliente que traiga su propia llave la
    | guarda cifrada en `ai_agents.api_key`, que gana sobre ésta.
    |
    | El SDK también lee ANTHROPIC_API_KEY del entorno por su cuenta, pero aquí
    | se pasa siempre explícita: así el agente de un cliente con llave propia no
    | acaba usando la nuestra por un descuido de configuración.
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Publishing — OAuth providers (Socialite)
    |--------------------------------------------------------------------------
    | Cada provider requiere registrar redirect_uri en su panel de desarrollador.
    | Las URLs callback son: {APP_URL}/social/oauth/{provider}/callback
    */
    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/social/oauth/facebook/callback',
        // Facebook Login for Business: los permisos y los activos (páginas,
        // cuentas de IG) los define una CONFIGURACIÓN en el panel de Meta, no
        // el parámetro `scope`. Sin config_id el diálogo solo concede perfil
        // básico y nunca ofrece elegir páginas.
        // Panel: Facebook Login for Business → Configurations.
        'login_config_id' => env('FACEBOOK_LOGIN_CONFIG_ID'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
        // Sin clave `scopes` a propósito. Socialite SÍ la lee
        // (SocialiteManager::buildProvider) y la FUSIONA con los scopes por
        // omisión del driver, así que tener la lista aquí además de en
        // SocialAuthController::scopesFor() producía la unión de las dos —
        // justo el bug que mandaba `email` al diálogo de Meta.
        // Fuente única: scopesFor(), aplicada con setScopes().
    ],

    'instagram' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/social/oauth/instagram/callback',
        // Instagram entra por el OAuth de Facebook. Puede tener su propia
        // configuración de Login for Business; si no se define, se reutiliza
        // la de Facebook siempre que incluya los activos de Instagram.
        'login_config_id' => env('INSTAGRAM_LOGIN_CONFIG_ID', env('FACEBOOK_LOGIN_CONFIG_ID')),
    ],
    
    'linkedin-openid' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/social/oauth/linkedin/callback',
        'scopes'        => [
            'openid',
            'profile',
            'email',
            'w_member_social',   // postear como usuario
            // 'w_organization_social', // páginas empresa — requiere aprobación LinkedIn
        ],
    ],

    'tiktok' => [
        'client_id'     => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/social/oauth/tiktok/callback',
    ],

    // YouTube se autentica con Google (Socialite core driver "google")
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/social/oauth/youtube/callback',
    ],

];
