<?php

use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Api\IdentityController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Middleware\EnsureApiConsumerIsActive;
use App\Models\ApiConsumer;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de plataforma — v1
|--------------------------------------------------------------------------
| Para que otros sistemas (klwebapp, las landings, workflows de n8n) usen el
| WhatsApp que administra esta aplicación, sin darle a cada uno su propia app
| de Meta ni copia de los tokens.
|
| Autenticación: token Bearer de Sanctum, emitido a un ApiConsumer.
|
|   Authorization: Bearer <token>
|
| El alcance NO lo elige el llamador: sale de `api_consumers.client_id`. Un
| consumidor atado a un cliente no puede tocar otro aunque mande su id.
|
| Los permisos por token son habilidades de Sanctum (ApiConsumer::ABILITIES),
| así que un token de solo lectura no puede enviar aunque llegue al endpoint.
|
| Sin estado: no hay sesión ni CSRF. Por eso este grupo no lleva el middleware
| `web` y las respuestas son siempre JSON.
*/

Route::prefix('v1')
    ->middleware(['auth:sanctum', EnsureApiConsumerIsActive::class])
    ->group(function () {
        // Diagnóstico. Sin habilidad: cualquier token válido puede preguntar
        // qué es — es justamente lo que se consulta cuando algo no funciona.
        Route::get('yo', [IdentityController::class, 'show'])->name('api.yo');

        Route::middleware('abilities:' . ApiConsumer::ABILITY_ENVIAR)->group(function () {
            Route::post('mensajes', [MessageController::class, 'store'])
                ->name('api.mensajes.store');

            // El camino que sirve con la ventana cerrada, y el único con el que
            // se puede INICIAR una conversación.
            Route::post('mensajes/plantilla', [MessageController::class, 'storeTemplate'])
                ->name('api.mensajes.plantilla');
        });

        Route::middleware('abilities:' . ApiConsumer::ABILITY_PLANTILLAS)->group(function () {
            Route::get('plantillas', [TemplateController::class, 'index'])
                ->name('api.plantillas.index');
        });

        Route::middleware('abilities:' . ApiConsumer::ABILITY_LEER)->group(function () {
            Route::get('conversaciones', [ConversationApiController::class, 'index'])
                ->name('api.conversaciones.index');

            Route::get('conversaciones/{conversacion}', [ConversationApiController::class, 'show'])
                ->whereNumber('conversacion')
                ->name('api.conversaciones.show');
        });
    });
