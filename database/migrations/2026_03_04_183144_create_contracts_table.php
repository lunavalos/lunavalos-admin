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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('status')->default('pending'); // pending, signed, voided
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('fiscal_address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('legal_representative')->nullable();
            $table->string('csf_file_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_ip')->nullable();
            $table->string('pdf_file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
