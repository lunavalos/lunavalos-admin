<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('media')->nullable(); // [{path, mime, role: image|video|thumbnail}]
            $table->json('options')->nullable(); // platform-specific overrides
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            // draft|scheduled|publishing|published|partial|failed|canceled
            $table->string('status', 16)->default('draft');
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
