<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Una WhatsApp Business Account (WABA). Puede ser la nuestra —con números
     * de varios clientes dentro— o la de un cliente que nos dio acceso vía
     * Embedded Signup.
     *
     * Por eso el cliente NO cuelga de aquí sino de whatsapp_numbers: es el
     * número, no la WABA, lo que determina a qué cliente pertenece una
     * conversación. Así los dos modelos conviven sin cambiar el esquema.
     */
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();

            // Nombre para distinguirlas en la UI ("LunAvalos", "Grupo Macadam").
            $table->string('name');

            // Enruta el webhook entrante: llega en entry[].id
            $table->string('waba_id')->unique();
            // Business portfolio dueño de la WABA. Null si es la nuestra.
            $table->string('business_id')->nullable();

            // Token del cliente obtenido vía Embedded Signup, cifrado en reposo
            // porque es credencial de un tercero. Null en nuestra propia WABA:
            // ahí se usa el system user token de services.whatsapp.token.
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->string('status')->default('active'); // active|revoked|error
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();

            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
