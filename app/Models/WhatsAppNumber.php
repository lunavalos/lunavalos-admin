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
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
