<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('provider', 32); // facebook, instagram, linkedin, tiktok, youtube
            $table->string('provider_user_id', 191);
            $table->string('name')->nullable();
            $table->string('handle')->nullable();
            $table->string('avatar_url', 1024)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('meta')->nullable(); // page_id, ig_business_id, channel_id, etc.
            $table->string('status', 16)->default('active'); // active|expired|revoked
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['client_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
