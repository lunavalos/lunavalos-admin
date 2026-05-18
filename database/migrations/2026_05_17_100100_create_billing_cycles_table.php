<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclos mensuales de un contrato. Cada ciclo es la unidad sobre la que se asignan
 * créditos y se contabilizan entregables.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['active', 'closed', 'locked'])->default('active');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Idempotencia: un contrato no puede tener dos ciclos con el mismo inicio.
            $table->unique(['contract_id', 'period_start']);
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
