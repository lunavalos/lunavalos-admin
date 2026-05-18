<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_account_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')
                ->constrained('social_accounts')
                ->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('followers')->default(0);
            $table->unsignedBigInteger('following')->default(0);
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->unsignedBigInteger('profile_views')->default(0);
            $table->unsignedBigInteger('page_impressions')->default(0);
            $table->unsignedBigInteger('page_reach')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'day'], 'social_acct_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_daily_stats');
    }
};
