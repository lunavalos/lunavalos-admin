<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `quote_addons`: relación entre cotizaciones y service_addons.
 * Guardamos snapshot de precio y ciclo para que cambios futuros del
 * catálogo no alteren la cotización emitida.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('quote_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('service_addon_id')->constrained('service_addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->string('billing_cycle');                    // snapshot
            $table->unsignedTinyInteger('billing_cycle_months')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['quote_id', 'service_addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_addons');
    }
};
