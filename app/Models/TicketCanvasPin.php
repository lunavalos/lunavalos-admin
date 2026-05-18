<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCanvasPin extends Model
{
    protected $fillable = [
        'canvas_item_id',
        'x_pct',
        'y_pct',
        'comment',
        'resolved',
        'user_id',
    ];

    protected $casts = [
        'x_pct' => 'float',
        'y_pct' => 'float',
        'resolved' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(TicketCanvasItem::class, 'canvas_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
