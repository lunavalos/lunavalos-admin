<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Consumo por agente y mes.
     *
     * Agregado y no una fila por llamada: la pregunta que hay que responder en
     * cada mensaje es "¿este cliente ya se pasó de su tope?", y con agregado se
     * contesta leyendo una fila en vez de sumando miles. El detalle de cada
     * llamada queda en el log.
     *
     * Los tokens de lectura de caché se cuentan aparte porque cuestan ~10% de
     * lo normal: mezclarlos haría que el tope castigara justo lo que abarata.
     */
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();

            $table->string('period', 7);                       // "2026-08"

            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('messages')->default(0);

            $table->timestamps();

            // Una fila por agente y mes: es lo que hace seguro el incremento
            // atómico desde varios workers a la vez.
            $table->unique(['ai_agent_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
