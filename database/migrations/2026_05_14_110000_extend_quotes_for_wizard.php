<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor de la tabla `quotes` para soportar el nuevo wizard:
 *
 * - package_service_id: paquete principal seleccionado.
 * - package_payment_plan_months: copia congelada del plan de pagos del paquete.
 * - subtotal / discount_amount / total: totales congelados al guardar
 *   (evita que el PDF cambie si el catálogo se mueve después).
 * - observations: notas opcionales que aparecen en el PDF.
 * - converted_at / converted_by_user_id: cuándo y quién la convirtió.
 * - legacy: marca cotizaciones del modelo viejo para que solo se abran en
 *   read-only y no se intenten editar en el wizard nuevo.
 *
 * Todos los campos son aditivos y nullable; los datos existentes siguen
 * funcionando con el QuoteController actual.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('package_service_id')->nullable()->after('client_id')
                ->constrained('services')->nullOnDelete();
            $table->unsignedTinyInteger('package_payment_plan_months')->nullable()->after('package_service_id');

            $table->decimal('subtotal', 12, 2)->default(0)->after('valid_until');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('total', 12, 2)->default(0)->after('discount_amount');

            $table->text('observations')->nullable()->after('notes');

            $table->timestamp('converted_at')->nullable()->after('observations');
            $table->foreignId('converted_by_user_id')->nullable()->after('converted_at')
                ->constrained('users')->nullOnDelete();

            $table->boolean('legacy')->default(false)->after('converted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_service_id');
            $table->dropConstrainedForeignId('converted_by_user_id');
            $table->dropColumn([
                'package_payment_plan_months',
                'subtotal',
                'discount_amount',
                'total',
                'observations',
                'converted_at',
                'legacy',
            ]);
        });
    }
};
