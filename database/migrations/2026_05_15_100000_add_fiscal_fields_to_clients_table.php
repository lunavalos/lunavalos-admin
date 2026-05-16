<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade campos fiscales (CFDI 4.0) a clients.
 * Aditiva: todos nullable o con defaults seguros para no romper clientes existentes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('rfc', 20)->nullable()->after('phone');
            $table->string('tax_regime', 5)->nullable()->after('rfc');           // c_RegimenFiscal
            $table->string('cfdi_use', 10)->nullable()->after('tax_regime');     // c_UsoCFDI
            $table->string('tax_zip_code', 10)->nullable()->after('cfdi_use');   // domicilio fiscal
            $table->string('fiscal_address')->nullable()->after('tax_zip_code'); // calle + número
            $table->string('legal_name')->nullable()->after('fiscal_address');   // razón social fiscal

            $table->boolean('applies_iva')->default(true)->after('legal_name');
            $table->decimal('iva_rate', 6, 4)->default(16.0000)->after('applies_iva');

            $table->boolean('applies_isr_retention')->default(false)->after('iva_rate');
            $table->decimal('isr_retention_rate', 6, 4)->default(0)->after('applies_isr_retention');

            $table->boolean('applies_iva_retention')->default(false)->after('isr_retention_rate');
            $table->decimal('iva_retention_rate', 6, 4)->default(0)->after('applies_iva_retention');

            $table->index('rfc');
            $table->index('tax_regime');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['rfc']);
            $table->dropIndex(['tax_regime']);
            $table->dropColumn([
                'rfc', 'tax_regime', 'cfdi_use', 'tax_zip_code', 'fiscal_address', 'legal_name',
                'applies_iva', 'iva_rate',
                'applies_isr_retention', 'isr_retention_rate',
                'applies_iva_retention', 'iva_retention_rate',
            ]);
        });
    }
};
