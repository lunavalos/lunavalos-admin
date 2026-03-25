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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE services MODIFY COLUMN billing_type ENUM('unique', 'monthly', 'annual') DEFAULT 'unique'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE quote_items MODIFY COLUMN billing_type ENUM('unique', 'monthly', 'annual') DEFAULT 'unique'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE services MODIFY COLUMN billing_type ENUM('unique', 'monthly') DEFAULT 'unique'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE quote_items MODIFY COLUMN billing_type ENUM('unique', 'monthly') DEFAULT 'unique'");
    }
};
