<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Un número de teléfono dentro de una WABA.
     *
     * Aquí vive client_id, no en whatsapp_accounts: es el número por el que
     * entró el mensaje lo que determina a qué cliente pertenece. Eso soporta
     * los dos modelos sin cambios:
     *
     *   - WABA propia con números de varios clientes (Standard Access).
     *   - WABA por cliente vía Embedded Signup (Advanced Access).
     */
    public function up(): void
    {
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            // Null = número propio de LunAvalos. No se inventa un Client para
            // representarnos a nosotros mismos.
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();

            // El que va en la URL de Graph al enviar, y el que llega en
            // value.metadata.phone_number_id al recibir.
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number');
            $table->string('verified_name')->nullable();

            // GREEN|YELLOW|RED, lo actualiza el webhook phone_number_quality_update
            $table->string('quality_rating')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_numbers');
    }
};
