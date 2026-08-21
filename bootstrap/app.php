<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API de plataforma: la usan klwebapp, las landings y n8n. Va sin
        // sesión ni CSRF, con token Bearer de Sanctum. Ver routes/api.php.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnforceTwoFactorActivation::class,
        ]);

        // Meta llama este webhook servidor a servidor — no manda token CSRF de
        // sesión. Va protegido con VerifyMetaSignature (ver routes/web.php).
        $middleware->validateCsrfTokens(except: [
            'whatsapp/webhook',
        ]);

        // Habilidades de los tokens de la API de plataforma. Sin este alias,
        // `->middleware('abilities:...')` en routes/api.php revienta al
        // resolverse, y un token de solo lectura podría enviar mensajes.
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        //
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
