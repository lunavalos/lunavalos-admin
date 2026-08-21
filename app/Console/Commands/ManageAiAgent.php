<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\AiUsage;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Alta y ajuste del agente de un cliente, más su consumo del mes.
 *
 * Por consola mientras no haya pantalla: lo que hace falta hoy es poder
 * encender un agente para un cliente piloto y ver cuánto lleva gastado.
 */
class ManageAiAgent extends Command
{
    protected $signature = 'ai:agente
        {cliente? : ID del cliente. Sin esto, el agente del número propio de LunAvalos}
        {--nombre= : Nombre del agente}
        {--encender : Deja el agente activo}
        {--apagar : Deja el agente inactivo}
        {--modelo= : Modelo a usar. Por omisión claude-opus-5}
        {--tope= : Tope mensual en tokens. "0" lo quita}
        {--prompt= : Prompt propio. Sin esto se arma con la ficha del cliente}
        {--consumo : Solo muestra el estado y el consumo del mes}';

    protected $description = 'Crea o ajusta el agente de IA de un cliente';

    public function handle(): int
    {
        $clienteId = $this->argument('cliente') ? (int) $this->argument('cliente') : null;

        if ($clienteId !== null && !Client::whereKey($clienteId)->exists()) {
            $this->error("No existe el cliente {$clienteId}.");

            return self::FAILURE;
        }

        $agente = AiAgent::where('client_id', $clienteId)->first();

        if ($this->option('consumo')) {
            return $this->mostrar($agente, $clienteId);
        }

        if (!$agente) {
            $agente = new AiAgent([
                'client_id' => $clienteId,
                'name'      => $this->option('nombre')
                    ?: 'Asistente de ' . (Client::find($clienteId)?->business_name ?? 'LunAvalos'),
            ]);
        }

        if ($nombre = $this->option('nombre')) {
            $agente->name = $nombre;
        }

        if ($modelo = $this->option('modelo')) {
            $agente->model = $modelo;
        }

        if ($prompt = $this->option('prompt')) {
            $agente->system_prompt = $prompt;
        }

        if ($this->option('tope') !== null) {
            $tope = (int) $this->option('tope');
            $agente->monthly_token_limit = $tope > 0 ? $tope : null;
        }

        if ($this->option('encender')) {
            $agente->enabled = true;
        }

        if ($this->option('apagar')) {
            $agente->enabled = false;
        }

        $agente->save();

        // Sin llave el agente no contesta y el síntoma sería silencio, que es
        // el peor modo de fallo: mejor decirlo aquí.
        if (!filled($agente->llaveApi())) {
            $this->warn('Ojo: no hay ANTHROPIC_API_KEY configurada. El agente no podrá responder.');
        }

        $this->info("Agente «{$agente->name}» guardado.");
        $this->newLine();

        return $this->mostrar($agente->fresh(), $clienteId);
    }

    private function mostrar(?AiAgent $agente, ?int $clienteId): int
    {
        if (!$agente) {
            $this->warn($clienteId
                ? "El cliente {$clienteId} no tiene agente. Créalo con: ai:agente {$clienteId} --encender"
                : 'No hay agente para el número propio de LunAvalos.');

            return self::SUCCESS;
        }

        $consumo = $agente->usage()->where('period', AiUsage::periodoActual())->first();
        $gastado = $consumo ? $consumo->input_tokens + $consumo->output_tokens : 0;

        $this->table(['Campo', 'Valor'], [
            ['Cliente',  $agente->client?->business_name ?? 'LunAvalos (número propio)'],
            ['Activo',   $agente->enabled ? 'sí' : 'no'],
            ['Modelo',   $agente->model],
            ['Prompt',   filled($agente->system_prompt) ? 'propio' : 'de la ficha del cliente'],
            ['Tope',     $agente->monthly_token_limit
                ? number_format($agente->monthly_token_limit) . ' tokens/mes'
                : 'sin tope'],
            ['Gastado (' . AiUsage::periodoActual() . ')', number_format($gastado) . ' tokens'],
            ['Leídos de caché', number_format($consumo?->cache_read_tokens ?? 0) . ' tokens (no cuentan al tope)'],
            ['Mensajes', number_format($consumo?->messages ?? 0)],
            ['Puede responder', $agente->puedeResponder() ? 'sí' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
