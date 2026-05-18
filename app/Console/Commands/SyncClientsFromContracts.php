<?php

namespace App\Console\Commands;

use App\Actions\Clients\SyncClientFromQuote;
use App\Actions\Quotes\ConvertQuoteToContract;
use App\Models\Contract;
use Illuminate\Console\Command;

/**
 * Backfill para clientes existentes:
 *  - Materializa Servicios y Costos Internos desde la cotización vinculada.
 *  - Rellena datos fiscales del cliente (sin sobrescribir lo capturado a mano).
 *  - Garantiza que el cronograma de cobranza (mensualidades) exista por contrato.
 *
 * 100% idempotente. Seguro ejecutarlo múltiples veces.
 */
class SyncClientsFromContracts extends Command
{
    protected $signature = 'clients:sync-from-contracts
                            {--client= : ID o email de un cliente específico}
                            {--dry-run : Muestra qué haría sin escribir}';

    protected $description = 'Sincroniza Servicios, Costos Internos y cronograma de cobranza de clientes a partir de sus contratos/cotizaciones.';

    public function handle(SyncClientFromQuote $sync, ConvertQuoteToContract $convert): int
    {
        $query = Contract::query()
            ->whereNotNull('quote_id')
            ->with(['client', 'quote.items.costs', 'quote.package', 'quote.addons.serviceAddon.costs']);

        if ($filter = $this->option('client')) {
            $query->whereHas('client', function ($q) use ($filter) {
                if (is_numeric($filter)) {
                    $q->where('id', (int) $filter);
                } else {
                    $q->where('email', $filter);
                }
            });
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->warn('No se encontraron contratos con cotización vinculada.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(sprintf('Procesando %d contrato(s)%s...', $contracts->count(), $dryRun ? ' [DRY-RUN]' : ''));

        $totalServices = 0;
        $totalCosts    = 0;
        $totalFiscal   = 0;
        $totalSchedule = 0;

        foreach ($contracts as $contract) {
            $client = $contract->client;
            $quote  = $contract->quote;

            if (!$client || !$quote) {
                $this->warn("Contrato #{$contract->id}: falta cliente o cotización, omitido.");
                continue;
            }

            $this->line(sprintf(
                ' • Contrato %s — cliente %s <%s>',
                $contract->contract_number ?? "#{$contract->id}",
                $client->business_name,
                $client->email
            ));

            if ($dryRun) {
                continue;
            }

            $result = $sync($client, $quote);
            $totalFiscal   += $result['fiscal'];
            $totalServices += $result['services'];
            $totalCosts    += $result['costs'];

            // Garantiza cronograma de cobranza (idempotente).
            $before = $contract->payments()->where('type', 'mensualidad')->count();
            $convert->generateSchedule($contract, $client);
            $after  = $contract->payments()->where('type', 'mensualidad')->count();
            $totalSchedule += max(0, $after - $before);
        }

        $this->newLine();
        $this->info('Resumen:');
        $this->line("  Campos fiscales rellenados : {$totalFiscal}");
        $this->line("  Servicios sincronizados    : {$totalServices}");
        $this->line("  Costos sincronizados       : {$totalCosts}");
        $this->line("  Mensualidades agendadas    : {$totalSchedule}");

        return self::SUCCESS;
    }
}
