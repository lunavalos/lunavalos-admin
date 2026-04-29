<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'client_id',
        'created_by',
        'title',
        'period_month',
        'period_year',
        'notes',
        'summary',
        'tickets_snapshot',
    ];

    protected $casts = [
        'period_month'      => 'integer',
        'period_year'       => 'integer',
        'tickets_snapshot'  => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'report_tickets')
                    ->withTrashed()
                    ->with(['creator', 'assigned', 'clientService'])
                    ->withPivot([]);
    }

    /** Human-readable period label, e.g. "Abril 2026" */
    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return ($months[$this->period_month] ?? '?') . ' ' . $this->period_year;
    }
}
