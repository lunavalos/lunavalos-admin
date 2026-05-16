<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade los campos necesarios para tratar a `contracts` como
 * el documento contractual completo (denormalizado, snapshot del Quote).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_number', 40)->nullable()->unique()->after('id');
            $table->foreignId('client_id')->nullable()->after('quote_id')
                ->constrained('clients')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('client_id')
                ->constrained('users')->nullOnDelete();

            $table->date('start_date')->nullable()->after('signed_at');
            $table->date('end_date')->nullable()->after('start_date');

            $table->unsignedTinyInteger('payment_plan_months')->nullable()->after('end_date');
            $table->decimal('subtotal',         12, 2)->default(0)->after('payment_plan_months');
            $table->decimal('discount_amount',  12, 2)->default(0)->after('subtotal');
            $table->decimal('iva_amount',       12, 2)->default(0)->after('discount_amount');
            $table->decimal('retentions_total', 12, 2)->default(0)->after('iva_amount');
            $table->decimal('total_amount',     12, 2)->default(0)->after('retentions_total');
            $table->decimal('monthly_amount',   12, 2)->default(0)->after('total_amount');
            $table->decimal('anticipo_amount',  12, 2)->default(0)->after('monthly_amount');

            $table->text('notes')->nullable()->after('anticipo_amount');

            $table->index('status');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['client_id']);
            $table->dropColumn([
                'contract_number', 'client_id', 'created_by_user_id',
                'start_date', 'end_date', 'payment_plan_months',
                'subtotal', 'discount_amount', 'iva_amount',
                'retentions_total', 'total_amount', 'monthly_amount',
                'anticipo_amount', 'notes',
            ]);
        });
    }
};
