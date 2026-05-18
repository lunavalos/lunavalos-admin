<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('provider', 32); // duplicated for fast query
            $table->string('status', 16)->default('pending'); // pending|publishing|published|failed|skipped
            $table->string('platform_post_id', 191)->nullable();
            $table->string('platform_url', 1024)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_account_id']);
            $table->index(['status', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
    }
};
