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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // 1. Datos Generales
            $table->string('business_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('agency_source')->nullable();

            // 2. Contrato y Renovaciones
            $table->date('contract_date')->nullable();
            $table->date('next_renewal_date')->nullable();
            $table->decimal('renewal_amount', 12, 2)->nullable();
            $table->string('package_services')->nullable();
            $table->boolean('auto_renew_notice')->default(true);

            // 3. Activos Técnicos y Accesos
            $table->string('domain_names')->nullable();
            $table->string('domain_provider')->nullable();
            $table->string('hosting_provider')->nullable();
            $table->string('email_provider')->nullable();
            $table->text('login_credentials')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
