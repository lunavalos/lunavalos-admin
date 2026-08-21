<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El agente de IA de un cliente.
 *
 * Uno por cliente: lo que distingue a un agente de otro es el negocio al que
 * representa. Que conteste o no en una conversación concreta lo sigue
 * decidiendo `conversations.ai_enabled`.
 */
class AiAgent extends Model
{
    protected $table = 'ai_agents';

    /** Aviso por omisión. Nunca se manda vacío: ver `avisoDeAutomatizacion()`. */
    public const DISCLOSURE_POR_OMISION = 'Hola, te responde un asistente automático. '
        . 'Si prefieres hablar con una persona, dilo y te paso con el equipo.';

    protected $fillable = [
        'client_id',
        'name',
        'enabled',
        'model',
        'system_prompt',
        'disclosure',
        'api_key',
        'monthly_token_limit',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'enabled'             => 'boolean',
            // Credencial de un tercero cuando el cliente trae la suya: cifrada,
            // igual que los tokens de WhatsApp.
            'api_key'             => 'encrypted',
            'monthly_token_limit' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }

    /**
     * La llave con la que llamar a la API.
     *
     * Por omisión la cuenta de LunAvalos, que es el modelo de cobro elegido:
     * pagamos nosotros y ponemos tope. Un cliente que traiga la suya la
     * sobreescribe sin que cambie nada más.
     */
    public function llaveApi(): ?string
    {
        return $this->api_key ?: (config('services.anthropic.api_key') ?: null);
    }

    /** El consumo del mes en curso, creando la fila si es el primer mensaje. */
    public function consumoDelMes(): AiUsage
    {
        return $this->usage()->firstOrCreate(['period' => AiUsage::periodoActual()]);
    }

    /**
     * ¿Se pasó del tope?
     *
     * Los tokens leídos de caché no cuentan: cuestan ~10% de lo normal, y
     * hacerlos contar castigaría justo lo que abarata el agente.
     */
    public function superoElTope(): bool
    {
        if ($this->monthly_token_limit === null) {
            return false;
        }

        $consumo = $this->usage()->where('period', AiUsage::periodoActual())->first();

        if (!$consumo) {
            return false;
        }

        return ($consumo->input_tokens + $consumo->output_tokens) >= $this->monthly_token_limit;
    }

    /**
     * ¿Puede contestar ahora mismo?
     *
     * Sin llave no se intenta siquiera: fallaría en la API y dejaría la
     * conversación sin respuesta igual, pero después de una llamada de red.
     */
    public function puedeResponder(): bool
    {
        return $this->enabled
            && filled($this->llaveApi())
            && !$this->superoElTope();
    }

    /**
     * El aviso de automatización. Nunca vacío: que el contacto sepa que le
     * contesta una máquina no es un detalle de tono, y dejarlo en manos de que
     * alguien rellene la columna es cómo se acaba sin avisar.
     */
    public function avisoDeAutomatizacion(): string
    {
        return filled($this->disclosure)
            ? $this->disclosure
            : self::DISCLOSURE_POR_OMISION;
    }
}
