<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Enlace ticket -> conversación que lo originó.
     *
     * Nullable porque la mayoría de los tickets no vienen de WhatsApp, y
     * porque la relación es opcional en el otro sentido: una conversación
     * puede vivir siempre sin generar un solo ticket.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('client_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
