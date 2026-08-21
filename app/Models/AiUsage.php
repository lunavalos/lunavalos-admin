<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consumo de un agente en un mes.
 *
 * Es lo que hace posible el modelo de cobro elegido —pagamos nosotros y
 * ponemos tope por cliente—: sin contar tokens en algún sitio, un tope es una
 * intención, no un límite.
 */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'ai_agent_id',
        'period',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'messages',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens'      => 'integer',
            'output_tokens'     => 'integer',
            'cache_read_tokens' => 'integer',
            'messages'          => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public static function periodoActual(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Suma el consumo de una llamada.
     *
     * Va por `increment` y no por leer-sumar-guardar a propósito: varios
     * workers de cola pueden estar respondiendo conversaciones del mismo
     * cliente a la vez, y leer-sumar-guardar pierde cuentas justo cuando más
     * tráfico hay — que es cuando el tope importa.
     */
    public function registrar(int $entrada, int $salida, int $cache = 0): void
    {
        $this->newQuery()->whereKey($this->getKey())->update([
            'input_tokens'      => $this->getConnection()->raw("input_tokens + {$entrada}"),
            'output_tokens'     => $this->getConnection()->raw("output_tokens + {$salida}"),
            'cache_read_tokens' => $this->getConnection()->raw("cache_read_tokens + {$cache}"),
            'messages'          => $this->getConnection()->raw('messages + 1'),
            'updated_at'        => now(),
        ]);
    }
}
