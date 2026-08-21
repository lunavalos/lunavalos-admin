<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Un sistema externo autorizado a usar esta plataforma: klwebapp, la
     * landing de un cliente, un workflow de n8n.
     *
     * No es un usuario. Un usuario entra por sesión y ve pantallas; un
     * consumidor entra por token y solo puede llamar a /api/v1. Separarlos
     * evita el atajo de crear un `User` de mentira por cada integración, que
     * arrastraría permisos de Spatie y sesión web sin necesitarlos.
     *
     * `client_id` sigue el mismo criterio que whatsapp_numbers (§4 del plan):
     *   - con valor → el consumidor queda ATADO a ese cliente y no puede
     *     tocar otro, aunque mande otro id en el cuerpo. Es el caso de la
     *     landing de Macadam.
     *   - null → consumidor interno de LunAvalos, que opera sobre varios
     *     clientes y debe decir en cada petición sobre cuál actúa. Es el caso
     *     de klwebapp.
     *
     * El token en sí NO vive aquí: lo guarda Sanctum en personal_access_tokens,
     * hasheado. Aquí solo vive quién es y qué puede tocar.
     */
    public function up(): void
    {
        Schema::create('api_consumers', function (Blueprint $table) {
            $table->id();

            $table->string('name');                       // "klwebapp", "landing-macadam"
            $table->string('slug')->unique();             // identificador estable en logs

            // Null = interno de LunAvalos (ver arriba).
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();

            // Entrega de entrantes. Sin URL, el consumidor es solo de salida:
            // puede mandar mensajes pero no se entera de las respuestas.
            $table->string('webhook_url')->nullable();

            // Con esto se firma cada entrega, igual que Meta firma las suyas.
            // Cifrado en reposo: es la llave que prueba que la entrega salió de
            // aquí, así que vale tanto como un token.
            $table->text('webhook_secret')->nullable();

            $table->string('status')->default('active');  // active|disabled
            $table->timestamp('last_used_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_consumers');
    }
};
