<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // kind: document | logo | branding | typography | color_palette | url | note
            $table->string('kind', 40);
            $table->string('label');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime')->nullable();
            $table->string('url')->nullable();
            // For palettes: ["#0B1E3F","#F39200"]. For typography: {"family":"Inter","weight":"600"}. Free-form notes etc.
            $table->json('value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_assets');
    }
};
