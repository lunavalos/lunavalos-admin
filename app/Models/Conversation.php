<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const STATUS_OPEN     = 'open';
    public const STATUS_SNOOZED  = 'snoozed';
    public const STATUS_ARCHIVED = 'archived';

    /** Ventana de servicio al cliente de Meta, en horas. */
    public const VENTANA_HORAS = 24;

    protected $fillable = [
        'client_id',
        'whatsapp_number_id',
        'contact_wa_id',
        'contact_name',
        'status',
        'assigned_id',
        'last_inbound_at',
        'last_message_at',
        'unread_count',
        'ai_enabled',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
            'unread_count'    => 'integer',
            'ai_enabled'      => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(WhatsAppNumber::class, 'whatsapp_number_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /** Tickets que salieron de esta conversación. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * ¿Se puede mandar texto libre?
     *
     * Meta solo entrega texto libre dentro de las 24 h siguientes al último
     * mensaje del contacto. Fuera de esa ventana responde 131047 y hace falta
     * una plantilla aprobada. Sin este control el mensaje se guarda, el envío
     * falla y nadie se entera de que el contacto nunca lo recibió.
     */
    public function ventanaAbierta(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->diffInHours(now()) < self::VENTANA_HORAS;
    }

    /**
     * Un mensaje entrante siempre reabre la conversación: archivarla no debe
     * esconder a un contacto que volvió a escribir.
     */
    public function registrarEntrante(?string $nombreContacto = null): void
    {
        $this->forceFill([
            'status'          => self::STATUS_OPEN,
            'contact_name'    => $nombreContacto ?: $this->contact_name,
            'last_inbound_at' => now(),
            'last_message_at' => now(),
            'unread_count'    => $this->unread_count + 1,
        ])->save();
    }

    public function registrarSaliente(): void
    {
        $this->forceFill([
            'last_message_at' => now(),
            'unread_count'    => 0,
        ])->save();
    }

    /**
     * ¿Le toca contestar al agente de IA?
     *
     * Dos condiciones, y la segunda es la que importa: **si alguien del equipo
     * tomó la conversación, la IA se calla**. Sin eso el bot contesta encima de
     * la persona que ya está atendiendo, que es la peor cara que puede dar un
     * negocio — dos voces distintas respondiendo lo mismo al mismo contacto.
     *
     * Asignarse una conversación pasa así a ser el modo de apagar el agente
     * sobre la marcha, sin buscar un interruptor.
     *
     * La ventana de 24 h no se comprueba aquí: el agente solo reacciona a un
     * entrante, así que por definición está abierta. `ConversationSender` la
     * valida igual, como con cualquier otro envío.
     */
    public function debeResponderIa(): bool
    {
        return $this->ai_enabled && $this->assigned_id === null;
    }
}
