<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade `due_date` a client_payments para soportar programación
 * de mensualidades (status='programado' hasta que se cobra).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('paid_at');
            $table->unsignedSmallInteger('installment_number')->nullable()->after('due_date');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_date']);
            $table->dropColumn(['due_date', 'installment_number']);
        });
    }
};
