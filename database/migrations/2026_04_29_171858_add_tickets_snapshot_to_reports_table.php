<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Stores a self-contained JSON snapshot of all ticket data at report-save time.
            // This ensures report data survives even if tickets are permanently deleted.
            $table->json('tickets_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('tickets_snapshot');
        });
    }
};
