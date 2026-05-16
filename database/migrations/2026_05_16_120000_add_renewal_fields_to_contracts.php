<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('renewal_status', 30)->default('none')->after('status');
            $table->timestamp('renewal_last_notified_at')->nullable()->after('renewal_status');
            $table->json('renewal_notifications_sent')->nullable()->after('renewal_last_notified_at');
            $table->foreignId('renewed_contract_id')->nullable()->after('renewal_notifications_sent')
                ->constrained('contracts')->nullOnDelete();
            $table->index(['renewal_status', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['renewed_contract_id']);
            $table->dropIndex(['renewal_status', 'end_date']);
            $table->dropColumn([
                'renewal_status',
                'renewal_last_notified_at',
                'renewal_notifications_sent',
                'renewed_contract_id',
            ]);
        });
    }
};
