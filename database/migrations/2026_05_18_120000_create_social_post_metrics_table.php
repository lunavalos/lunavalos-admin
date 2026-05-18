<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_post_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_target_id')
                ->constrained('social_post_targets')
                ->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('video_views')->default(0);
            $table->decimal('engagement_rate', 6, 4)->default(0); // 0.0000 - 1.0000
            $table->json('raw')->nullable(); // payload completo de la API
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique('social_post_target_id', 'social_post_metrics_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_metrics');
    }
};
