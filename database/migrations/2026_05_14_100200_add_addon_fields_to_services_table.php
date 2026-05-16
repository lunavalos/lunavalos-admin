<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla `services` con campos para el nuevo flujo del wizard:
 *
 * - required_addon_category: si está set, el wizard obliga al usuario a
 *   elegir al menos un addon con esa categoría al cotizar este paquete.
 * - payment_plan_months: en cuántos meses se divide el costo del paquete
 *   (1..24). Solo informativo para finanzas; el saldo se calcula por pagos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('required_addon_category')->nullable()->after('is_package');
            $table->unsignedTinyInteger('payment_plan_months')->default(1)->after('required_addon_category');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['required_addon_category', 'payment_plan_months']);
        });
    }
};
