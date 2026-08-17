<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espejo local de una plantilla de mensaje de Meta.
 *
 * Solo las APPROVED se pueden enviar. El resto se guardan igual porque el
 * equipo necesita ver que una plantilla está esperando revisión o que Meta la
 * rechazó y por qué.
 */
class WhatsAppTemplate extends Model
{
    // Sin esto Laravel deriva "whats_app_templates" del CamelCase del modelo.
    protected $table = 'whatsapp_templates';

    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PAUSED   = 'PAUSED';
    public const STATUS_DISABLED = 'DISABLED';

    public const CATEGORIAS = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];

    protected $fillable = [
        'whatsapp_account_id',
        'meta_id',
        'name',
        'language',
        'category',
        'status',
        'rejected_reason',
        'components',
        'body_variables',
    ];

    protected function casts(): array
    {
        return [
            'components'     => 'array',
            'body_variables' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function estaAprobada(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** El texto del componente BODY, que es el que lleva las variables. */
    public function cuerpo(): string
    {
        foreach ($this->components ?? [] as $componente) {
            if (($componente['type'] ?? '') === 'BODY') {
                return $componente['text'] ?? '';
            }
        }

        return '';
    }

    /**
     * Cómo se verá el mensaje con estos parámetros. Se guarda como `body` del
     * ConversationMessage: en el hilo tiene que leerse el texto real que
     * recibió el contacto, no el nombre de la plantilla.
     */
    public function previsualizar(array $parametros): string
    {
        $texto = $this->cuerpo();

        foreach (array_values($parametros) as $i => $valor) {
            $texto = str_replace('{{' . ($i + 1) . '}}', (string) $valor, $texto);
        }

        return $texto;
    }

    /**
     * Cuenta los {{n}} distintos del cuerpo. Meta los exige consecutivos desde
     * 1, así que el máximo es también la cantidad.
     */
    public static function contarVariables(string $cuerpo): int
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $cuerpo, $coincidencias);

        return $coincidencias[1] ? max(array_map('intval', $coincidencias[1])) : 0;
    }
}
