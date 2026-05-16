<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot fiscal en cada cotización. Permite que el cliente cambie su
 * configuración SAT en el futuro sin alterar cotizaciones ya emitidas.
 *
 * Recalcula `total` como: subtotal - discount + IVA trasladado
 *                        - ISR retenido - IVA retenido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('tax_regime', 5)->nullable()->after('discount_amount');

            $table->boolean('applies_iva')->default(false)->after('tax_regime');
            $table->decimal('iva_rate', 6, 4)->default(0)->after('applies_iva');
            $table->decimal('iva_amount', 12, 2)->default(0)->after('iva_rate');

            $table->boolean('applies_isr_retention')->default(false)->after('iva_amount');
            $table->decimal('isr_retention_rate', 6, 4)->default(0)->after('applies_isr_retention');
            $table->decimal('isr_retention_amount', 12, 2)->default(0)->after('isr_retention_rate');

            $table->boolean('applies_iva_retention')->default(false)->after('isr_retention_amount');
            $table->decimal('iva_retention_rate', 6, 4)->default(0)->after('applies_iva_retention');
            $table->decimal('iva_retention_amount', 12, 2)->default(0)->after('iva_retention_rate');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'tax_regime',
                'applies_iva', 'iva_rate', 'iva_amount',
                'applies_isr_retention', 'isr_retention_rate', 'isr_retention_amount',
                'applies_iva_retention', 'iva_retention_rate', 'iva_retention_amount',
            ]);
        });
    }
};
