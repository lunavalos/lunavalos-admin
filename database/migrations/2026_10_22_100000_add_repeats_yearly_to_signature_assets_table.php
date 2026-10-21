<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logos de temporada que vuelven solos cada año (Halloween, Navidad…): solo
 * cuentan el día y el mes de `starts_at` / `ends_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_assets', function (Blueprint $table) {
            $table->boolean('repeats_yearly')->default(false)->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('signature_assets', function (Blueprint $table) {
            $table->dropColumn('repeats_yearly');
        });
    }
};
