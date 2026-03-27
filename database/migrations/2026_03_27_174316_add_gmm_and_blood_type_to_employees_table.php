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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('blood_type')->nullable()->after('address');
            $table->string('gmm_policy')->nullable()->after('blood_type');
            $table->string('gmm_insurer')->nullable()->after('gmm_policy');
            $table->string('gmm_advisor_name')->nullable()->after('gmm_insurer');
            $table->string('gmm_advisor_phone')->nullable()->after('gmm_advisor_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['blood_type', 'gmm_policy', 'gmm_insurer', 'gmm_advisor_name', 'gmm_advisor_phone']);
        });
    }
};
