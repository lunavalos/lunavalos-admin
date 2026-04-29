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
        Schema::create('client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->string('service_name');
            $table->date('renewal_date')->nullable();
            $table->decimal('renewal_amount', 12, 2)->nullable();
            $table->enum('status', ['active', 'inactive', 'cancelled'])->default('active');
            $table->timestamps();
        });

        // Migrate existing data
        $clients = \Illuminate\Support\Facades\DB::table('clients')->get();
        foreach ($clients as $client) {
            if ($client->package_services) {
                // We create at least one record for the existing package
                \Illuminate\Support\Facades\DB::table('client_services')->insert([
                    'client_id'      => $client->id,
                    'service_name'   => $client->package_services,
                    'renewal_date'   => $client->next_renewal_date,
                    'renewal_amount' => $client->renewal_amount,
                    'status'         => 'active',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_services');
    }
};
