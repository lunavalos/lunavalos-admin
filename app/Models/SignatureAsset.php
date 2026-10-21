<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SignatureAsset extends Model
{
    public const SLOTS = ['logo', 'banner'];

    protected $fillable = [
        'client_id',
        'slot',
        'label',
        'file_path',
        'mime',
        'link_url',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $hoy));
    }

    /**
     * El que se sirve hoy para ese cliente y espacio.
     *
     * Uno de temporada (con fecha de inicio) le gana al de siempre; entre
     * varios de temporada gana el que empezó más reciente, y a igualdad, el
     * último subido.
     */
    public static function current(int $clientId, string $slot): ?self
    {
        return static::query()
            ->where('client_id', $clientId)
            ->where('slot', $slot)
            ->vigentes()
            ->orderByRaw('starts_at is null')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }
}
