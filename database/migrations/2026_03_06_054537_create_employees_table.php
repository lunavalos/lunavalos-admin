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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('employee_number')->unique();
            $table->string('phone')->nullable();
            $table->string('curp')->nullable();
            $table->string('nss')->nullable();
            $table->string('rfc')->nullable();
            $table->text('address')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('join_date')->nullable();
            $table->decimal('initial_salary', 15, 2)->default(0);
            $table->decimal('current_salary', 15, 2)->default(0);
            $table->string('status')->default('Activo'); // Activo, Inactivo, Suspendido
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
