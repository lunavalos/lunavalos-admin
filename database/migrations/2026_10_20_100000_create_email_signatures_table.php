<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una firma por empleado de un cliente.
 *
 * El empleado no es usuario del sistema: recibe un link público con `token`
 * para copiar su firma. Las imágenes de la firma apuntan a URLs fijas con ese
 * mismo token, así que cambiar el logo de la empresa actualiza las firmas que
 * ya están instaladas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Vacío = usa la plantilla asignada al cliente.
            $table->foreignId('signature_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_signatures');
    }
};
