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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('priority')->default('Media'); // Baja, Media, Alta, Urgente
            $table->string('status')->default('Nuevos'); // Nuevos, En Proceso, En Revisión, Ajustes, Completados
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
