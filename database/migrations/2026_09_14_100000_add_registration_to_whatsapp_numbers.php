<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Registro del número en Cloud API.
     *
     * Conceder la WABA por Embedded Signup y suscribir la app al webhook no
     * basta: hasta que el número no se registra con
     * `POST /{phone_number_id}/register`, Meta no lo considera activo en Cloud
     * API y cualquier envío falla. Era el único paso del flujo que no estaba.
     *
     * - `status` es lo que Meta reporta del número (CONNECTED, PENDING, …) y
     *   es lo que decide si hace falta registrarlo. Sin este dato habría que
     *   registrar a ciegas cada vez que se sincroniza.
     * - `registration_pin` es el PIN de verificación en dos pasos que fijamos
     *   al registrar. Va cifrado: es una credencial del número del cliente, y
     *   sin ella no se puede volver a registrar ni migrar a otro proveedor.
     * - `registration_error` existe para que el fallo no sea invisible. El
     *   registro no tumba la conexión —la WABA y el webhook sí quedaron—, así
     *   que sin esta columna el síntoma aparecería después como "los envíos no
     *   salen" sin nada que lo explique.
     */
    public function up(): void
    {
        Schema::table('whatsapp_numbers', function (Blueprint $table) {
            $table->string('status')->nullable()->after('quality_rating');
            $table->timestamp('registered_at')->nullable()->after('status');
            $table->text('registration_pin')->nullable()->after('registered_at');
            $table->text('registration_error')->nullable()->after('registration_pin');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_numbers', function (Blueprint $table) {
            $table->dropColumn(['status', 'registered_at', 'registration_pin', 'registration_error']);
        });
    }
};
