<?php

namespace App\Console\Commands;

use App\Actions\Recurring\OpenBillingCycle;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OpenBillingCyclesCommand extends Command
{
    protected $signature = 'recurring:open-cycles
                            {--contract= : ID de un contrato específico (opcional)}
                            {--month= : Mes a abrir en formato Y-m (default: mes actual)}';

    protected $description = 'Abre el ciclo mensual de cada contrato recurrente y precarga los entregables fijos.';

    public function handle(OpenBillingCycle $openCycle): int
    {
        $periodStart = $this->option('month')
            ? CarbonImmutable::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $query = Contract::query()
            ->where('status', 'signed')
            ->whereHas('contractServices');

        if ($this->option('contract')) {
            $query->where('id', $this->option('contract'));
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->info('No hay contratos recurrentes que procesar.');
            return self::SUCCESS;
        }

        $opened = 0;
        foreach ($contracts as $contract) {
            try {
                $cycle = $openCycle($contract, $periodStart);
                $this->line(sprintf(
                    '  ✓ Contrato #%d (%s) → Ciclo %s',
                    $contract->id,
                    $contract->contract_number,
                    $cycle->period_start->format('Y-m')
                ));
                $opened++;
            } catch (\Throwable $e) {
                $this->error(sprintf('  ✗ Contrato #%d: %s', $contract->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('Listo. %d ciclo(s) procesado(s).', $opened));
        return self::SUCCESS;
    }
}
