<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency rollout.
 *
 * Estrategia:
 *  - Cada documento monetario lleva (currency, exchange_rate). `exchange_rate`
 *    es la tasa snapshot doc.currency → moneda base (config('currencies.base'))
 *    al momento de fijar el importe. Si currency == base, exchange_rate = 1.
 *  - Catálogos (services, service_addons) sólo llevan `currency` (define en
 *    qué divisa se cotiza por defecto el precio de catálogo).
 *  - Tablas que YA tenían `currency` (invoices, client_payments) sólo reciben
 *    `exchange_rate`.
 *
 * El default en todos los casos es 'MXN' / 1.00000000 para no romper datos
 * históricos. Backfill sin riesgo: todo lo viejo queda implícitamente MXN.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Documentos: currency + exchange_rate snapshot ---
        $this->addCurrency('quotes', after: 'discount_amount');
        $this->addCurrency('contracts', after: 'discount_amount');
        $this->addCurrency('client_services', after: 'renewal_amount');
        $this->addCurrency('client_costs', after: 'amount');

        // --- Sólo exchange_rate (currency ya existe) ---
        Schema::table('client_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('client_payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency');
            }
            // Campo opcional para CFDIs convertidos: importe original cuando el
            // pago se cobró en otra moneda y el CFDI se timbra en MXN.
            if (! Schema::hasColumn('invoices', 'original_currency')) {
                $table->string('original_currency', 3)->nullable()->after('exchange_rate');
            }
            if (! Schema::hasColumn('invoices', 'original_amount')) {
                $table->decimal('original_amount', 14, 2)->nullable()->after('original_currency');
            }
        });

        // --- Catálogos: sólo currency ---
        $this->addCurrencyOnly('services', after: 'renewal_price');
        $this->addCurrencyOnly('service_addons', after: 'price');
    }

    public function down(): void
    {
        foreach (['quotes', 'contracts', 'client_services', 'client_costs'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'exchange_rate')) {
                    $table->dropColumn('exchange_rate');
                }
                if (Schema::hasColumn($t, 'currency')) {
                    $table->dropColumn('currency');
                }
            });
        }

        Schema::table('client_payments', function (Blueprint $table) {
            if (Schema::hasColumn('client_payments', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['exchange_rate', 'original_currency', 'original_amount'] as $c) {
                if (Schema::hasColumn('invoices', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        foreach (['services', 'service_addons'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'currency')) {
                    $table->dropColumn('currency');
                }
            });
        }
    }

    /** Documento: currency (default MXN) + exchange_rate (default 1). */
    private function addCurrency(string $table, ?string $after = null): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $after) {
            if (! Schema::hasColumn($table, 'currency')) {
                $col = $blueprint->string('currency', 3)->default('MXN');
                if ($after) $col->after($after);
            }
            if (! Schema::hasColumn($table, 'exchange_rate')) {
                $col = $blueprint->decimal('exchange_rate', 18, 8)->default(1);
                if ($after) $col->after('currency');
            }
        });
    }

    /** Catálogo: solo currency. */
    private function addCurrencyOnly(string $table, ?string $after = null): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $after) {
            if (! Schema::hasColumn($table, 'currency')) {
                $col = $blueprint->string('currency', 3)->default('MXN');
                if ($after) $col->after($after);
            }
        });
    }
};
