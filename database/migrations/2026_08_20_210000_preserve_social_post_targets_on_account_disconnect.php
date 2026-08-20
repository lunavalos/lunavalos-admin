<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desconectar una red ya no borra el historial de publicaciones.
 *
 * `social_post_targets.social_account_id` era `cascadeOnDelete` y
 * `SocialAuthController::disconnect()` borra la cuenta de verdad —
 * `SocialAccount` no usa SoftDeletes—. Resultado: al desconectar Facebook e
 * Instagram desaparecían TODOS los targets de esas cuentas, incluidos los de
 * posts ya publicados, con su `platform_post_id` y su enlace. Los posts se
 * quedaban en el calendario sin ninguna red y sin forma de recuperarlos:
 * reconectar crea una fila nueva porque la vieja ya no existe.
 *
 * Con `nullOnDelete` el target sobrevive a la desconexión. `provider` ya está
 * duplicado en la propia fila justo para esto, así que la etiqueta de red y el
 * enlace se siguen mostrando aunque la cuenta ya no esté.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->dropForeign(['social_account_id']);
        });

        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->change();
            $table->foreign('social_account_id')
                ->references('id')->on('social_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Volver a `cascadeOnDelete` exige que no queden huérfanos: la columna
        // no admitiría NULL y esas filas son justo las que esta migración
        // existe para conservar.
        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->dropForeign(['social_account_id']);
        });

        \Illuminate\Support\Facades\DB::table('social_post_targets')
            ->whereNull('social_account_id')
            ->delete();

        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable(false)->change();
            $table->foreign('social_account_id')
                ->references('id')->on('social_accounts')
                ->cascadeOnDelete();
        });
    }
};
