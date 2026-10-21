<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SignatureAsset extends Model
{
    public const SLOTS = ['logo', 'banner'];

    protected $fillable = [
        'client_id',
        'signature_template_id',
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

    public function signatureTemplate()
    {
        return $this->belongsTo(SignatureTemplate::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $hoy));
    }

    /**
     * El que se sirve hoy para ese cliente, espacio y plantilla.
     *
     * Primero los de la plantilla de la firma; si no hay, los que aplican a
     * todas las plantillas del cliente. Dentro de cada grupo, uno de temporada
     * (con fecha de inicio) le gana al de siempre.
     */
    public static function current(int $clientId, string $slot, ?int $templateId = null): ?self
    {
        return ($templateId ? static::currentInScope($clientId, $slot, $templateId) : null)
            ?? static::currentInScope($clientId, $slot, null);
    }

    /**
     * El vigente dentro de un solo grupo: una plantilla, o `null` = el que
     * aplica a todas. Entre varios de temporada gana el que empezó más
     * reciente, y a igualdad, el último subido.
     */
    public static function currentInScope(int $clientId, string $slot, ?int $templateId): ?self
    {
        return static::query()
            ->where('client_id', $clientId)
            ->where('slot', $slot)
            ->when(
                $templateId,
                fn ($q) => $q->where('signature_template_id', $templateId),
                fn ($q) => $q->whereNull('signature_template_id')
            )
            ->vigentes()
            ->orderByRaw('starts_at is null')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * El logo configurado como URL en el expediente del cliente, ya absoluto.
     *
     * Se acepta relativo (`/storage/...`) porque así lo guardan a veces; en un
     * correo una ruta relativa no carga, por eso se completa con el dominio.
     */
    public static function fallbackLogoUrl(?Client $client): ?string
    {
        $logo = trim((string) ($client?->signature_defaults['logo'] ?? ''));

        if ($logo === '') {
            return null;
        }

        if (str_starts_with($logo, '//')) {
            return 'https:' . $logo;
        }

        return preg_match('#^https?://#i', $logo) ? $logo : url($logo);
    }

    /** ¿Una firma de este cliente con esa plantilla tiene logo que mostrar hoy? */
    public static function hasLogo(Client $client, ?int $templateId = null): bool
    {
        return static::current($client->id, 'logo', $templateId) !== null || static::fallbackLogoUrl($client) !== null;
    }
}
