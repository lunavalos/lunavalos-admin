<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Una conversación de WhatsApp: un contacto hablando por un número.
     *
     * No es un ticket. Un ticket se abre, se trabaja y se cierra; una
     * conversación no se cierra — el contacto vuelve meses después por otro
     * asunto en el mismo hilo. Cuando de una conversación sale trabajo que hay
     * que rastrear, se crea un ticket enlazado (tickets.conversation_id).
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Null cuando la conversación entró por el número propio de
            // LunAvalos: no pertenece a ningún cliente externo.
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_number_id')->constrained();

            $table->string('contact_wa_id');             // teléfono del contacto
            $table->string('contact_name')->nullable();  // nombre de perfil de WhatsApp

            // Estado de BANDEJA, no ciclo de vida de trabajo: archivada vuelve
            // a open sola si el contacto escribe de nuevo.
            $table->string('status')->default('open');   // open|snoozed|archived
            $table->foreignId('assigned_id')->nullable()->constrained('users')->nullOnDelete();

            // Ventana de 24 h de Meta: si last_inbound_at tiene más de 24 h, el
            // texto libre ya no se entrega y hay que usar plantilla aprobada.
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            $table->boolean('ai_enabled')->default(false);
            $table->timestamps();

            // Un contacto tiene UNA conversación por número. Sin esto se recae
            // en abrir un hilo por mensaje, que es justo el problema que este
            // módulo viene a resolver.
            $table->unique(['whatsapp_number_id', 'contact_wa_id']);
            $table->index(['client_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
