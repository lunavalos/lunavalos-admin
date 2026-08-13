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
    | n8n — pasarela de WhatsApp
    |--------------------------------------------------------------------------
    | Este sistema no habla con graph.facebook.com. El token de Meta, el
    | phone_number_id y el verify_token del webhook viven en n8n, que es
    | quien enruta hacia el número que corresponde a cada cliente.
    |
    | Salida:  Laravel -> whatsapp_webhook_url (con X-N8n-Secret)
    | Entrada: Meta -> n8n (valida X-Hub-Signature-256) -> {APP_URL}/whatsapp/webhook
    |
    | shared_secret protege ambas direcciones y debe ser el mismo valor
    | configurado del lado de n8n.
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
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'), // actualiza a v21
        'scopes'        => [
            'email',
            'public_profile',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',       // publicar en páginas
            'instagram_basic',
            'instagram_content_publish', // publicar en IG Business
        ],
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
