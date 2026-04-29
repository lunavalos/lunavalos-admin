<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'priority',
        'status',
        'creator_id',
        'assigned_id',
        'client_id',
        'client_service_id',
        'due_date',
        'status_updated_at',
        'work_started_at',
        'work_finished_at',
    ];

    protected $casts = [
        'due_date'         => 'datetime',
        'status_updated_at' => 'datetime',
        'work_started_at'  => 'datetime',
        'work_finished_at' => 'datetime',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientService()
    {
        return $this->belongsTo(ClientService::class, 'client_service_id');
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
