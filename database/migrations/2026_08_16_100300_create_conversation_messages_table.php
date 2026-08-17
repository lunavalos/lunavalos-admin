<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // user_id es null cuando habla el contacto o cuando responde la IA.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_type');            // contact|staff|ai
            $table->string('direction');              // in|out

            // Idempotencia del webhook: Meta reintenta el mismo evento.
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('type')->default('text');  // text|image|document|audio|…
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();

            // Lo que hoy falta y hace invisibles los fallos de envío: hasta
            // ahora un mensaje rechazado por Meta se guardaba igual y nadie se
            // enteraba de que el contacto nunca lo recibió.
            $table->string('delivery_status')->default('pending'); // pending|sent|delivered|read|failed
            $table->text('delivery_error')->nullable();

            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
