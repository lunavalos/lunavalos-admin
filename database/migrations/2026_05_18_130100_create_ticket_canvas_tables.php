<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_canvas_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            // type: image | video | pdf | url
            $table->string('type', 20);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime')->nullable();
            $table->string('url')->nullable();
            $table->string('caption')->nullable();
            // pending | approved | changes_requested
            $table->string('approval_status', 30)->default('pending');
            $table->text('approval_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'position']);
        });

        Schema::create('ticket_canvas_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_item_id')->constrained('ticket_canvas_items')->cascadeOnDelete();
            $table->decimal('x_pct', 6, 3); // 0–100
            $table->decimal('y_pct', 6, 3);
            $table->text('comment');
            $table->boolean('resolved')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('canvas_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_canvas_pins');
        Schema::dropIfExists('ticket_canvas_items');
    }
};
