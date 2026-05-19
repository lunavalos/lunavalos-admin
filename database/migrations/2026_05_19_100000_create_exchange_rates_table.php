<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Almacén histórico de tipos de cambio.
 *
 * - Una fila por (fecha, par from→to, fuente). La unicidad garantiza idempotencia
 *   al ingestar Banxico y permite override manual con `source='manual'`.
 * - `rate` se guarda con 8 decimales (precisión suficiente para par MXN/USD y
 *   acordes a la práctica Banxico).
 * - Lectura: `App\Support\Money\CurrencyService::rate($from, $to, $date)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date')->index();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('source', 32)->default('manual'); // banxico|manual|fixer|...
            $table->timestamps();

            $table->unique(
                ['rate_date', 'from_currency', 'to_currency', 'source'],
                'exchange_rates_unique_per_day_source'
            );
            $table->index(['from_currency', 'to_currency', 'rate_date'], 'exchange_rates_pair_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
