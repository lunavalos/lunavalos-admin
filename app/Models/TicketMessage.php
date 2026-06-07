<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'file_path',
        'channel',
        'direction',
        'wa_message_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
