<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->decimal('initial_payment', 10, 2)->default(0)->after('renewal_amount');
            $table->decimal('initial_cost', 10, 2)->default(0)->after('initial_payment');
        });
    }

    public function down(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->dropColumn(['initial_payment', 'initial_cost']);
        });
    }
};
