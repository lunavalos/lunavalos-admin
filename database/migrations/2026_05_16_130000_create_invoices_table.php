<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `invoices`: registros CFDI emitidos vía Facturama.
 * Una factura puede ligarse a un ClientPayment (PUE) o a un Contract (PPD).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_payment_id')->nullable()->constrained('client_payments')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Datos del CFDI
            $table->string('facturama_id')->nullable()->index();   // Id en Facturama
            $table->string('uuid', 64)->nullable()->unique();      // Folio Fiscal SAT
            $table->string('series', 20)->nullable();
            $table->string('folio', 20)->nullable();

            $table->string('cfdi_type', 5)->default('I');          // I=Ingreso, E=Egreso, P=Pago
            $table->string('payment_method', 5)->default('PUE');   // PUE / PPD
            $table->string('payment_form', 5)->nullable();         // c_FormaPago (01, 03, ...)
            $table->string('cfdi_use', 10)->nullable();
            $table->string('currency', 5)->default('MXN');

            $table->decimal('subtotal',  12, 2)->default(0);
            $table->decimal('discount',  12, 2)->default(0);
            $table->decimal('taxes',     12, 2)->default(0);
            $table->decimal('retentions', 12, 2)->default(0);
            $table->decimal('total',     12, 2)->default(0);

            $table->string('status', 20)->default('issued');       // issued | canceled | error
            $table->string('cancellation_status', 20)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // Almacenamiento local del XML / PDF.
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();

            // Snapshot del payload y respuesta para auditoría.
            $table->json('request_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
