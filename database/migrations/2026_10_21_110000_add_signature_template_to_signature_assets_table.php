<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logos y banners por plantilla dentro de un mismo cliente.
 *
 * Una cuenta puede agrupar varias empresas (Absolute maneja dos marcas con
 * plantillas distintas). Vacío = aplica a todas las plantillas del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_assets', function (Blueprint $table) {
            $table->foreignId('signature_template_id')->nullable()->after('client_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signature_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_template_id');
        });
    }
};
