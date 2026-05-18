<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de "entregables mensuales" que están incluidos en un contrato.
 * Define la cuota mensual (ej. 8 Reels, 4 Blogs) y cómo se materializan en tickets.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_addon_id')->nullable()->constrained()->nullOnDelete();

            // Display
            $table->string('name');                 // ej. "Reels", "Blogs"
            $table->string('prefix', 8)->default('T'); // ej. "R", "B", "EM"
            $table->string('color', 16)->nullable();   // hex para UI

            // Cuota
            $table->unsignedInteger('quantity_per_cycle')->default(0);
            $table->enum('unit_type', ['fixed', 'on_demand_pool', 'unlimited'])->default('fixed');
            $table->boolean('rollover_allowed')->default(false);

            // Auto-pre-cargar tickets en el backlog al abrir el ciclo
            $table->boolean('auto_create_tickets')->default(true);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['contract_id', 'unit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_services');
    }
};
