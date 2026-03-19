<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('imap_host')->nullable()->after('email_provider');
            $table->string('imap_port')->nullable()->after('imap_host');
            $table->boolean('imap_tls')->default(true)->after('imap_port');

            $table->string('pop_host')->nullable()->after('imap_tls');
            $table->string('pop_port')->nullable()->after('pop_host');
            $table->boolean('pop_tls')->default(true)->after('pop_port');

            $table->string('smtp_host')->nullable()->after('pop_tls');
            $table->string('smtp_port')->nullable()->after('smtp_host');
            $table->boolean('smtp_tls')->default(true)->after('smtp_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_tls',
                'pop_host',
                'pop_port',
                'pop_tls',
                'smtp_host',
                'smtp_port',
                'smtp_tls'
            ]);
        });
    }
};
