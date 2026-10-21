<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imágenes (íconos, logos, separadores) que usa el HTML de una plantilla de
 * firma, servidas desde nuestro dominio para no depender del sitio del cliente.
 *
 * Al crear una plantilla todavía no hay id: las imágenes se suben con un
 * `draft_key` y se ligan a la plantilla al guardarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('draft_key', 64)->nullable()->index();
            $table->string('name');
            $table->string('file_path');
            $table->string('mime')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_media');
    }
};
