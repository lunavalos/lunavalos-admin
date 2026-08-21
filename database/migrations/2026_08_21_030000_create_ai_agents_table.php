<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * El agente de IA de un cliente: su prompt, su modelo y su tope de gasto.
     *
     * Uno por cliente, no uno por conversación: lo que distingue a un agente
     * de otro es el negocio al que representa. `conversations.ai_enabled`
     * sigue decidiendo conversación por conversación si contesta o no.
     *
     * `client_id` nullable y único sigue el criterio de §4: null es el agente
     * del número propio de LunAvalos.
     */
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();

            // Único: un cliente tiene un agente. Nullable = el de LunAvalos.
            $table->foreignId('client_id')->nullable()->unique()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->boolean('enabled')->default(false);

            $table->string('model')->default('claude-opus-5');

            // Null = se arma con la ficha del cliente (briefing_*), que ya
            // está capturada. Con valor, lo escrito aquí manda: es la vía para
            // afinar un agente concreto sin tocar la ficha comercial.
            $table->text('system_prompt')->nullable();

            // Aviso de automatización del primer mensaje. Que sea una columna y
            // no una constante permite adaptarlo al tono de cada negocio, pero
            // el código no deja que quede vacío.
            $table->string('disclosure')->nullable();

            // Opción C del modelo de cobro: por omisión se usa la cuenta de
            // LunAvalos; un cliente que traiga la suya la guarda aquí, cifrada
            // como cualquier credencial de un tercero.
            $table->text('api_key')->nullable();

            // Tope mensual en tokens. Null = sin tope. Al alcanzarlo el agente
            // deja de responder y la conversación queda para el equipo, que es
            // preferible a una factura sorpresa.
            $table->unsignedBigInteger('monthly_token_limit')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
