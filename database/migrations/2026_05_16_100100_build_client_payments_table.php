<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Construye la tabla `client_payments` (registro de pagos del cliente:
 * anticipo, mensualidades, pagos únicos, ajustes).
 *
 * La tabla ya existía vacía; aquí añadimos todas las columnas reales.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')
                ->constrained('clients')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->after('client_id')
                ->constrained('contracts')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->after('contract_id')
                ->constrained('quotes')->nullOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->after('quote_id')
                ->constrained('users')->nullOnDelete();

            // anticipo | mensualidad | pago_unico | ajuste | reembolso
            $table->string('type', 30)->default('mensualidad')->after('registered_by_user_id');
            // registrado | conciliado | facturado | cancelado
            $table->string('status', 20)->default('registrado')->after('type');

            $table->string('concept', 255)->nullable()->after('status');
            $table->decimal('amount', 12, 2)->default(0)->after('concept');
            $table->string('currency', 3)->default('MXN')->after('amount');

            // efectivo | transferencia | tarjeta | cheque | otro
            $table->string('payment_method', 30)->nullable()->after('currency');
            $table->string('reference', 100)->nullable()->after('payment_method');
            $table->date('paid_at')->nullable()->after('reference');

            $table->string('evidence_file_path')->nullable()->after('paid_at');
            $table->text('notes')->nullable()->after('evidence_file_path');

            $table->index(['client_id', 'paid_at']);
            $table->index(['contract_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['contract_id']);
            $table->dropForeign(['quote_id']);
            $table->dropForeign(['registered_by_user_id']);
            $table->dropIndex(['client_id', 'paid_at']);
            $table->dropIndex(['contract_id', 'type']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'client_id', 'contract_id', 'quote_id', 'registered_by_user_id',
                'type', 'status', 'concept', 'amount', 'currency',
                'payment_method', 'reference', 'paid_at',
                'evidence_file_path', 'notes',
            ]);
        });
    }
};
