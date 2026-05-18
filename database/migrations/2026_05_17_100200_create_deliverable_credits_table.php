<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Bolsa" mensual: cuántos créditos hay disponibles, cuántos se consumieron y
 * cuántos vinieron de rollover. Es la fuente de verdad para los contadores R-03/08.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deliverable_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_service_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('total')->default(0);          // créditos del ciclo (sin rollover)
            $table->unsignedInteger('rolled_over')->default(0);    // adicionales heredados del ciclo previo
            $table->unsignedInteger('consumed')->default(0);       // consumidos en este ciclo
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();

            $table->unique(['billing_cycle_id', 'contract_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_credits');
    }
};
