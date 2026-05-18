<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_canvas_items', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained('ticket_canvas_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('stack_position')->default(0)->after('position');
            $table->index(['parent_id', 'stack_position']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_canvas_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'stack_position']);
            $table->dropColumn(['parent_id', 'stack_position']);
        });
    }
};
