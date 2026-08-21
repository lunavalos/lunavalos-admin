<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * Sistema externo con acceso a /api/v1: klwebapp, una landing, un workflow.
 *
 * Ver la migración para el criterio de `client_id`. El resumen: con valor, el
 * consumidor está atado a ese cliente; null significa interno de LunAvalos y
 * obliga a nombrar el cliente en cada petición.
 */
class ApiConsumer extends Model
{
    use HasApiTokens;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';

    /** Habilidades de Sanctum. Un token lleva las que se le den al crearlo. */
    public const ABILITY_ENVIAR      = 'mensajes:enviar';
    public const ABILITY_LEER        = 'conversaciones:leer';
    public const ABILITY_PLANTILLAS  = 'plantillas:leer';

    public const ABILITIES = [
        self::ABILITY_ENVIAR,
        self::ABILITY_LEER,
        self::ABILITY_PLANTILLAS,
    ];

    protected $fillable = [
        'name',
        'slug',
        'client_id',
        'webhook_url',
        'webhook_secret',
        'status',
        'last_used_at',
        'created_by',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            // Con esta llave se firma cada entrega saliente: vale lo mismo que
            // un token, así que va cifrada como los de WhatsApp.
            'webhook_secret' => 'encrypted',
            'last_used_at'   => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estaActivo(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** ¿Está atado a un solo cliente, o elige en cada petición? */
    public function estaAtado(): bool
    {
        return $this->client_id !== null;
    }

    /**
     * ¿Puede actuar sobre este cliente?
     *
     * Un consumidor atado solo puede sobre el suyo. Uno interno puede sobre
     * cualquiera — es de LunAvalos, no de un tercero.
     */
    public function puedeOperarSobre(?int $clientId): bool
    {
        if (!$this->estaAtado()) {
            return true;
        }

        return $this->client_id === $clientId;
    }

    /** ¿Recibe los entrantes por webhook? */
    public function recibeWebhooks(): bool
    {
        return $this->estaActivo()
            && filled($this->webhook_url)
            && filled($this->webhook_secret);
    }
}
