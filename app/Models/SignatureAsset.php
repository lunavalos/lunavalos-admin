<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
        'repeats_yearly',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
        'repeats_yearly' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function signatureTemplate()
    {
        return $this->belongsTo(SignatureTemplate::class);
    }

    /**
     * ¿Se muestra ese día?
     *
     * Sin fechas: siempre. Con `repeats_yearly` solo cuentan día y mes, y el
     * rango puede cruzar de año (15 dic → 6 ene).
     */
    public function isActiveOn(CarbonInterface $day): bool
    {
        if ($this->repeats_yearly && $this->starts_at && $this->ends_at) {
            $hoy = $day->format('m-d');
            $desde = $this->starts_at->format('m-d');
            $hasta = $this->ends_at->format('m-d');

            return $desde <= $hasta
                ? $hoy >= $desde && $hoy <= $hasta
                : $hoy >= $desde || $hoy <= $hasta;
        }

        $fecha = $day->toDateString();

        return (! $this->starts_at || $this->starts_at->toDateString() <= $fecha)
            && (! $this->ends_at || $this->ends_at->toDateString() >= $fecha);
    }

    /**
     * Cuándo empezó la vigencia actual; para los que se repiten, el inicio de
     * la temporada de este ciclo. `null` = sin fecha de inicio (el de siempre).
     */
    public function currentStart(CarbonInterface $day): ?CarbonInterface
    {
        if (! $this->starts_at) {
            return null;
        }

        if (! $this->repeats_yearly) {
            return $this->starts_at;
        }

        $inicio = $this->starts_at->copy()->setYear($day->year);

        return $inicio->greaterThan($day) ? $inicio->subYear() : $inicio;
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
     *
     * Se filtra en PHP y no en SQL porque la regla de los que se repiten cada
     * año compara día y mes; son pocos registros por cliente.
     */
    public static function currentInScope(int $clientId, string $slot, ?int $templateId): ?self
    {
        $hoy = now()->startOfDay();

        return static::query()
            ->where('client_id', $clientId)
            ->where('slot', $slot)
            ->when(
                $templateId,
                fn ($q) => $q->where('signature_template_id', $templateId),
                fn ($q) => $q->whereNull('signature_template_id')
            )
            ->get()
            ->filter(fn (self $a) => $a->isActiveOn($hoy))
            ->sort(function (self $a, self $b) use ($hoy) {
                $inicioA = $a->currentStart($hoy);
                $inicioB = $b->currentStart($hoy);

                // Los de temporada primero, luego el más reciente, luego el último subido.
                return [$inicioB !== null, $inicioB?->timestamp, $b->id]
                    <=> [$inicioA !== null, $inicioA?->timestamp, $a->id];
            })
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
