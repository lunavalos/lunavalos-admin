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
    | WhatsApp — webhook de entrada (Meta directo)
    |--------------------------------------------------------------------------
    | Meta llama a {APP_URL}/whatsapp/webhook:
    |   GET  -> handshake, se compara hub.verify_token contra verify_token.
    |   POST -> eventos, se valida X-Hub-Signature-256 con app_secret.
    |
    | verify_token lo elegimos nosotros y se copia tal cual en el panel de Meta.
    | app_secret es el App Secret de la app (Settings > Basic).
    |
    | La salida todavía va por n8n (ver bloque siguiente); cuando pase a hablar
    | con Graph directo hará falta también el token por cliente.
    */
    'whatsapp' => [
        'app_id'         => env('WHATSAPP_APP_ID'),
        'app_secret'     => env('WHATSAPP_APP_SECRET'),
        'verify_token'   => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version'  => env('WHATSAPP_GRAPH_VERSION', 'v26.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n — pasarela de WhatsApp (solo salida)
    |--------------------------------------------------------------------------
    | Solo para ENVIAR. Este sistema todavía no habla con graph.facebook.com:
    | el token de Meta y el phone_number_id viven en n8n, que es quien enruta
    | hacia el número que corresponde a cada cliente.
    |
    | Salida:  Laravel -> whatsapp_webhook_url (con X-N8n-Secret)
    | Entrada: Meta -> {APP_URL}/whatsapp/webhook — n8n ya no participa.
    |
    | shared_secret debe ser el mismo valor configurado del lado de n8n.
    */
    'n8n' => [
        'whatsapp_webhook_url' => env('N8N_WHATSAPP_WEBHOOK_URL'),
        'shared_secret'        => env('N8N_SHARED_SECRET'),
        'timeout'              => env('N8N_TIMEOUT', 10),
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
        // Instagram usa el mismo flujo Facebook — los scopes los maneja facebook arriba
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
