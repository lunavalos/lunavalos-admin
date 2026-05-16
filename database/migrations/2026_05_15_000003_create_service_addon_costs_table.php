<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costos internos por addon, análogo a `service_costs` para servicios.
 * Permite medir utilidad real de cotizaciones que incluyen addons recurrentes
 * (hosting, dominios, software de terceros, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addon_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_addon_id')
                ->constrained('service_addons')
                ->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addon_costs');
    }
};
