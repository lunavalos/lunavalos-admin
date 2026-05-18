<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla de tickets para soportar el módulo de Clientes Recurrentes.
 * - source_type: separa visualmente "support" (kanban actual) de "recurring".
 * - billing_cycle_id / deliverable_credit_id: trazabilidad del crédito consumido.
 * - sequence_number: número correlativo dentro del crédito (para el código R-03/08).
 * Todos los tickets existentes quedan como 'support' por default → cero ruptura.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('source_type', ['support', 'recurring'])
                ->default('support')
                ->after('status')
                ->index();

            $table->foreignId('billing_cycle_id')
                ->nullable()
                ->after('client_service_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('deliverable_credit_id')
                ->nullable()
                ->after('billing_cycle_id')
                ->constrained()
                ->nullOnDelete();

            $table->unsignedInteger('sequence_number')->nullable()->after('deliverable_credit_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['billing_cycle_id']);
            $table->dropForeign(['deliverable_credit_id']);
            $table->dropColumn(['source_type', 'billing_cycle_id', 'deliverable_credit_id', 'sequence_number']);
        });
    }
};
