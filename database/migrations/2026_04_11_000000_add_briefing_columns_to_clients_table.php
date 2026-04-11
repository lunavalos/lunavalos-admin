<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('briefing_context')->nullable();
            $table->text('briefing_target_audience')->nullable();
            $table->text('briefing_competitors')->nullable();
            $table->text('briefing_references')->nullable();
            $table->text('briefing_contact_methods')->nullable();
            $table->text('briefing_current_emails')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'briefing_context',
                'briefing_target_audience',
                'briefing_competitors',
                'briefing_references',
                'briefing_contact_methods',
                'briefing_current_emails'
            ]);
        });
    }
};
