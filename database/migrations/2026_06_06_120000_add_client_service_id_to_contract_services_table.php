<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_services', function (Blueprint $table) {
            $table->foreignId('client_service_id')
                  ->nullable()
                  ->after('service_addon_id')
                  ->constrained('client_services')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_services', function (Blueprint $table) {
            $table->dropForeign(['client_service_id']);
            $table->dropColumn('client_service_id');
        });
    }
};
