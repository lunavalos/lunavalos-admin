<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // 'web' (creado desde la app) | 'whatsapp' (conversación de WhatsApp)
            $table->string('channel')->default('web')->after('source_type');
            // wa_id del contacto de WhatsApp; permite enrutar respuestas aunque
            // el teléfono guardado en clients no coincida en formato.
            $table->string('whatsapp_wa_id')->nullable()->after('channel');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->string('channel')->default('web')->after('file_path');
            // 'in' (cliente -> nosotros) | 'out' (nosotros -> cliente); null para mensajes web
            $table->string('direction')->nullable()->after('channel');
            // id del mensaje en WhatsApp; evita duplicar al reprocesar el webhook
            $table->string('wa_message_id')->nullable()->unique()->after('direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['channel', 'whatsapp_wa_id']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn(['channel', 'direction', 'wa_message_id']);
        });
    }
};
