<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * El WhatsApp de cada usuario.
     *
     * Hace falta porque el aviso de ticket dejó de ir a un número fijo: ahora
     * se le manda a la persona afectada por el cambio —el responsable del
     * ticket—, sea interna o del cliente. Sin este dato no hay a dónde mandar.
     *
     * Se guarda ya normalizado a wa_id (dígitos con lada), no como lo escribió
     * la persona: ver `App\Support\TelefonoWhatsApp`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
