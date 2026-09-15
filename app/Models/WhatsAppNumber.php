<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppNumber extends Model
{
    // Sin esto Laravel deriva "whats_app_numbers" del CamelCase del modelo.
    protected $table = 'whatsapp_numbers';

    protected $fillable = [
        'whatsapp_account_id',
        'client_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'status',
        'registered_at',
        'registration_pin',
        'registration_error',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'registered_at' => 'datetime',
            // Credencial del número del cliente: mismo criterio que el token
            // de WhatsAppAccount.
            'registration_pin' => 'encrypted',
        ];
    }

    /**
     * Estado que Meta reporta cuando el número ya está activo en Cloud API.
     */
    public const ESTADO_CONECTADO = 'CONNECTED';

    /**
     * Un número conectado pero sin registrar no puede enviar: Meta responde
     * con error a cualquier llamada a /messages. Es el estado que la UI tiene
     * que gritar, porque desde fuera se ve idéntico a uno sano.
     */
    public function necesitaRegistro(): bool
    {
        return $this->status !== self::ESTADO_CONECTADO;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Token con el que enviar desde este número.
     */
    public function tokenParaEnviar(): ?string
    {
        return $this->account?->tokenParaEnviar();
    }
}
