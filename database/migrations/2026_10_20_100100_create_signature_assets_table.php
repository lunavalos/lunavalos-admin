<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logos y banners de firma por cliente, con vigencia opcional.
 *
 * Sin fechas = el de siempre. Con fechas = de temporada: mientras está
 * vigente le gana al de siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 20); // logo | banner
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('mime')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_assets');
    }
};
