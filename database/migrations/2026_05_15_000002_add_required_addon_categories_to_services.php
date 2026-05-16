<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que un servicio requiera N categorías de addons (no solo una).
 * Conserva la columna vieja `required_addon_category` para evitar romper código
 * heredado durante la transición; las nuevas lecturas/escrituras usan el JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // SQLite no soporta nativamente `json`, pero Laravel mapea a TEXT y
            // el cast 'array' del modelo se encarga de serializar.
            $table->json('required_addon_categories')->nullable()->after('required_addon_category');
        });

        // Backfill: convierte el valor único existente en arreglo JSON con 1 elemento.
        DB::table('services')
            ->whereNotNull('required_addon_category')
            ->where('required_addon_category', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('services')
                    ->where('id', $row->id)
                    ->update([
                        'required_addon_categories' => json_encode([$row->required_addon_category]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('required_addon_categories');
        });
    }
};
