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
            $table->dropColumn([
                'package_services',
                'next_renewal_date',
                'renewal_amount',
                'auto_renew_notice'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('package_services')->nullable();
            $table->date('next_renewal_date')->nullable();
            $table->decimal('renewal_amount', 15, 2)->nullable();
            $table->boolean('auto_renew_notice')->default(true);
        });
    }
};
