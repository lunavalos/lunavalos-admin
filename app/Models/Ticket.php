<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'title',
        'content',
        'priority',
        'status',
        'creator_id',
        'assigned_id',
        'due_date',
        'status_updated_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'status_updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ticket) {
            if ($ticket->isDirty('status') || !$ticket->exists) {
                if (!$ticket->isDirty('status_updated_at')) {
                    $ticket->status_updated_at = now();
                }
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assigned()
    {
        return $this->belongsTo(User::class, 'assigned_id');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
