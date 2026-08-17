<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAccount extends Model
{
    // Sin esto Laravel deriva "whats_app_accounts" del CamelCase del modelo.
    protected $table = 'whatsapp_accounts';

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_ERROR   = 'error';

    protected $fillable = [
        'name',
        'waba_id',
        'business_id',
        'access_token',
        'token_expires_at',
        'status',
        'last_error_at',
        'last_error',
        'connected_by',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            // Es credencial de un tercero: no basta con ocultarla de las
            // respuestas, tiene que estar cifrada en reposo.
            'access_token'     => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_error_at'    => 'datetime',
        ];
    }

    public function numbers(): HasMany
    {
        // Clave foránea explícita: del CamelCase del modelo Laravel derivaría
        // "whats_app_account_id".
        return $this->hasMany(WhatsAppNumber::class, 'whatsapp_account_id');
    }

    public function connectedBy()
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * Token con el que hablarle a Graph. En nuestra propia WABA no hay token
     * guardado y se usa el del system user que vive en configuración.
     */
    public function tokenParaEnviar(): ?string
    {
        return $this->access_token ?: (config('services.whatsapp.token') ?: null);
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
