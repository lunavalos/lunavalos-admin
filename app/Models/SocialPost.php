<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SocialPost extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_SCHEDULED  = 'scheduled';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED  = 'published';
    public const STATUS_PARTIAL    = 'partial';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELED   = 'canceled';

    /**
     * Rol del adjunto dentro de `media`. La portada viaja en el mismo arreglo
     * que el resto de los archivos —así se duplica y se borra con el post sin
     * columnas extra—, pero nunca se publica como contenido: es la imagen que
     * la red usa de carátula del video.
     */
    public const ROLE_COVER = 'cover';

    protected $fillable = [
        'client_id', 'ticket_id', 'title', 'body', 'media', 'options',
        'scheduled_at', 'published_at', 'status', 'error_message',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $appends = ['cover_url', 'media_url', 'media_mime'];

    protected $casts = [
        'media'        => 'array',
        'options'      => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    /**
     * Los adjuntos que sí se publican: todo menos la portada.
     *
     * Los publishers tienen que usar esto y no `media` directo; si no, una
     * portada subida junto a un reel se colaba como `media[0]` y se publicaba
     * la imagen en lugar del video.
     */
    public function mediaPrincipal(): array
    {
        return array_values(array_filter(
            $this->media ?? [],
            fn ($m) => !is_array($m) || ($m['role'] ?? null) !== self::ROLE_COVER,
        ));
    }

    /** La imagen de portada del video, si se subió una. */
    public function portada(): ?array
    {
        foreach ($this->media ?? [] as $m) {
            if (is_array($m) && ($m['role'] ?? null) === self::ROLE_COVER) {
                return $m;
            }
        }

        return null;
    }

    /** URL pública de la portada, para previsualizarla en el compositor. */
    public function getCoverUrlAttribute(): ?string
    {
        $path = $this->portada()['path'] ?? null;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * El primer adjunto publicable, que es el único que sale a las redes.
     *
     * Viaja al compositor para la vista previa: `media` solo guarda la ruta en
     * disco y el frontend no puede armar la URL pública por su cuenta.
     */
    public function getMediaUrlAttribute(): ?string
    {
        $path = $this->rutaDelPrimerAdjunto();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function getMediaMimeAttribute(): ?string
    {
        $primero = $this->mediaPrincipal()[0] ?? null;

        return is_array($primero) ? ($primero['mime'] ?? null) : null;
    }

    private function rutaDelPrimerAdjunto(): ?string
    {
        $primero = $this->mediaPrincipal()[0] ?? null;

        return is_array($primero) ? ($primero['path'] ?? null) : $primero;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    public function recomputeStatus(): void
    {
        $this->loadMissing('targets');
        $statuses = $this->targets->pluck('status');
        if ($statuses->isEmpty()) return;

        if ($statuses->every(fn ($s) => $s === SocialPostTarget::STATUS_PUBLISHED)) {
            $this->status       = self::STATUS_PUBLISHED;
            $this->published_at = $this->published_at ?? now();
        } elseif ($statuses->contains(SocialPostTarget::STATUS_PUBLISHED)) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($statuses->every(fn ($s) => $s === SocialPostTarget::STATUS_FAILED)) {
            $this->status = self::STATUS_FAILED;
        } elseif ($statuses->contains(SocialPostTarget::STATUS_PUBLISHING)) {
            $this->status = self::STATUS_PUBLISHING;
        }
        $this->save();
    }
}
