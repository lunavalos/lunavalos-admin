<?php

use App\Models\Quote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de estados de cotizaciones existentes al nuevo workflow.
 *
 * Mapeo:
 *   Pendiente        -> Enviada
 *   Aceptada         -> Aceptada
 *   Rechazada        -> Rechazada
 *   Completada       -> Convertida
 *   Contrato Firmado -> Convertida
 *
 * Todas las quotes previas se marcan `legacy=1` para que la UI nueva
 * las muestre en modo solo-lectura y no intente reabrirlas en el wizard.
 */
return new class extends Migration {
    public function up(): void
    {
        $map = [
            'Pendiente'        => 'Enviada',
            'Aceptada'         => 'Aceptada',
            'Rechazada'        => 'Rechazada',
            'Completada'       => 'Convertida',
            'Contrato Firmado' => 'Convertida',
        ];

        foreach ($map as $old => $new) {
            DB::table('quotes')->where('status', $old)->update(['status' => $new]);
        }

        DB::table('quotes')->update(['legacy' => 1]);
    }

    public function down(): void
    {
        // No revertimos el estado: información se perdería. Solo desmarcamos legacy.
        DB::table('quotes')->update(['legacy' => 0]);
    }
};
