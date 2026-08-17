<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    public const AUTHOR_CONTACT = 'contact';
    public const AUTHOR_STAFF   = 'staff';
    public const AUTHOR_AI      = 'ai';

    public const DELIVERY_PENDING   = 'pending';
    public const DELIVERY_SENT      = 'sent';
    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_READ      = 'read';
    public const DELIVERY_FAILED    = 'failed';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'author_type',
        'direction',
        'wa_message_id',
        'type',
        'body',
        'media_path',
        'delivery_status',
        'delivery_error',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fallo(): bool
    {
        return $this->delivery_status === self::DELIVERY_FAILED;
    }
}
