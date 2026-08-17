<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Plantillas de mensaje de una WABA.
     *
     * Son lo único que Meta entrega fuera de la ventana de 24 h, así que sin
     * esta tabla la mitad de las conversaciones quedan sin salida: el equipo
     * escribe, Meta responde 131047 y el contacto nunca recibe nada.
     *
     * La plantilla vive en Meta, no aquí: esta tabla es un espejo local. Se
     * guarda `meta_id` para poder emparejar los eventos de
     * `message_template_status_update`, que es como nos enteramos de que una
     * plantilla pasó de PENDING a APPROVED o REJECTED — puede tardar horas y
     * nadie va a estar refrescando el panel de Meta.
     */
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();

            // Id de la plantilla en Meta. Nullable porque al crearla puede que
            // la respuesta no lo traiga; se rellena en la siguiente sync.
            $table->string('meta_id')->nullable()->index();

            $table->string('name');
            $table->string('language', 20);           // es_MX, en_US…
            $table->string('category');               // MARKETING|UTILITY|AUTHENTICATION
            $table->string('status')->default('PENDING');
            $table->text('rejected_reason')->nullable();

            // Los componentes tal cual los entiende Graph. Guardarlos completos
            // evita reconstruirlos al enviar y al previsualizar.
            $table->json('components');

            // Cuántos {{n}} tiene el cuerpo. Se deriva de components, pero
            // tenerlo aquí permite validar el envío sin parsear el JSON.
            $table->unsignedTinyInteger('body_variables')->default(0);

            $table->timestamps();

            // Meta identifica una plantilla por nombre + idioma dentro de la
            // WABA; la misma pareja no puede existir dos veces.
            $table->unique(['whatsapp_account_id', 'name', 'language'], 'whatsapp_templates_unicas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
