<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCanvasItem extends Model
{
    protected $fillable = [
        'ticket_id',
        'parent_id',
        'position',
        'stack_position',
        'type',
        'file_path',
        'file_name',
        'mime',
        'url',
        'caption',
        'approval_status',
        'approval_note',
        'uploaded_by',
    ];

    public const TYPES = ['image', 'video', 'pdf', 'url'];
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CHANGES = 'changes_requested';

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('stack_position');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function pins()
    {
        return $this->hasMany(TicketCanvasPin::class, 'canvas_item_id')->orderBy('created_at');
    }
}
