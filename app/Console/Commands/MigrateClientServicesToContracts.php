<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MigrateClientServicesToContracts extends Command
{
    protected $signature = 'clients:migrate-to-contracts
        {--dry-run   : Muestra qué se crearía sin escribir nada en la BD}
        {--client=   : Procesar solo un cliente específico (por ID)}
        {--force     : Omitir confirmación antes de escribir}';

    protected $description = 'Crea contratos formales a partir del legacy de ClientService (Desglose de Servicios / Renovaciones Individuales).';

    private int $contractSeq = 0;

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $onlyId  = $this->option('client');
        $today   = Carbon::today();
        $overdueAfter = (int) config('quotes.renewals.mark_overdue_after_days', 7);

        // ── Banner ──────────────────────────────────────────────────────────
        $this->newLine();
        if ($dryRun) {
            $this->warn('╔══════════════════════════════════════════════════════╗');
            $this->warn('║  MODO DRY-RUN — no se escribirá nada en la BD       ║');
            $this->warn('╚══════════════════════════════════════════════════════╝');
        } else {
            $this->info('╔══════════════════════════════════════════════════════╗');
            $this->info('║  MIGRACIÓN REAL — se crearán contratos en la BD     ║');
            $this->info('╚══════════════════════════════════════════════════════╝');
        }
        $this->newLine();

        // ── Inicializar secuencia de contract_number ────────────────────────
        $this->initContractSeq();

        // ── Cargar clientes candidatos ──────────────────────────────────────
        // Solo clientes con client_services activos y SIN ningún contrato todavía.
        $query = Client::withCount('contracts')
            ->with([
                'services' => fn ($q) => $q->where('status', 'active')->orderBy('renewal_date'),
            ]);

        if ($onlyId) {
            $query->where('id', $onlyId);
        } else {
            $query->has('services');
        }

        $clients = $query->get()->filter(fn ($c) => $c->contracts_count === 0);

        if ($clients->isEmpty()) {
            $this->info('No hay clientes pendientes de migración. ¡Todo está al día!');
            $this->reportContractsWithoutEndDate();
            return self::SUCCESS;
        }

        $this->line("  Clientes candidatos: <fg=cyan>{$clients->count()}</>");
        $this->newLine();

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm("Se crearán hasta {$clients->count()} contratos. ¿Continuar?")) {
                $this->warn('Operación cancelada.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        // ── Procesar cada cliente ───────────────────────────────────────────
        $created = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            $services = $client->services; // ya filtrados activos

            if ($services->isEmpty()) {
                $skipped++;
                continue;
            }

            [$endDate, $startDate, $multipleRenewalDates] = $this->computeDates($services, $today);
            [$totalAmount, $monthlyAmount]                 = $this->computeAmounts($services);
            $renewalStatus                                 = $this->computeRenewalStatus($endDate, $today, $overdueAfter);

            // ── Salida del preview ──────────────────────────────────────────
            $this->line("┌─ <fg=cyan>{$client->business_name}</> (ID #{$client->id})");

            foreach ($services as $s) {
                $date   = $s->renewal_date ? $s->renewal_date->format('d/m/Y') : '<fg=yellow>sin fecha</>';
                $amount = $s->renewal_amount ? '$' . number_format((float) $s->renewal_amount, 2) : '<fg=yellow>$0.00</>';
                $type   = $s->billing_type ?? 'annual';
                $this->line("│   · {$s->service_name}");
                $this->line("│     vence: {$date}  |  monto: {$amount}  |  tipo: {$type}");
            }

            if ($multipleRenewalDates) {
                $this->line('│   <fg=yellow>⚠ Servicios con fechas distintas — se usa la más próxima como end_date</>');
            }

            $this->line('│');
            $this->line("│   end_date:      " . ($endDate ? "<fg=white>{$endDate->format('d/m/Y')}</>" : '<fg=red>sin fecha — quedará null</>'));
            $this->line("│   start_date:    {$startDate->format('d/m/Y')}");
            $this->line("│   total_amount:  $" . number_format($totalAmount, 2));
            $this->line("│   monthly_ref:   $" . number_format($monthlyAmount, 2) . ' /mes');

            $statusColor = match ($renewalStatus) {
                'overdue'  => 'red',
                'pending'  => 'yellow',
                default    => 'green',
            };
            $this->line("│   renewal_status: <fg={$statusColor}>{$renewalStatus}</>");

            if (! $dryRun) {
                $contractNumber = $this->nextContractNumber();
                $contract = Contract::create([
                    'client_id'           => $client->id,
                    'token'               => Str::random(40),
                    'contract_number'     => $contractNumber,
                    'legal_name'          => $client->business_name,
                    'start_date'          => $startDate->toDateString(),
                    'end_date'            => $endDate?->toDateString(),
                    'total_amount'        => $totalAmount,
                    'monthly_amount'      => $monthlyAmount,
                    'status'              => 'signed',
                    'renewal_status'      => $renewalStatus,
                    'payment_plan_months' => 12,
                    'notes'               => $this->buildNotes($services),
                ]);
                // Enlazar los client_services a este contrato
                \App\Models\ClientService::where('client_id', $client->id)
                    ->whereIn('id', $services->pluck('id'))
                    ->update(['contract_id' => $contract->id]);
                $this->line("└─ <fg=green>✓ Contrato {$contractNumber} creado.</>");
            } else {
                $previewNumber = $this->nextContractNumber();
                $this->line("└─ <fg=gray>[dry-run] Se crearía contrato {$previewNumber}</>");
            }

            $this->newLine();
            $created++;
        }

        // ── Resumen ─────────────────────────────────────────────────────────
        $this->line('══════════════════════════════════════════════════════');
        if ($dryRun) {
            $this->warn("DRY-RUN finalizado.");
            $this->line("  Se <fg=cyan>crearían</> <fg=white>{$created}</> contratos.");
            if ($skipped > 0) {
                $this->line("  <fg=yellow>{$skipped}</> clientes sin servicios activos (saltados).");
            }
            $this->newLine();
            $this->comment('Para migrar en real ejecuta:');
            $this->comment('  php artisan clients:migrate-to-contracts');
            $this->comment('  php artisan clients:migrate-to-contracts --force   (sin confirmación)');
        } else {
            $this->info("Migración completa.");
            $this->line("  <fg=green>✓</> {$created} contratos creados.");
            if ($skipped > 0) {
                $this->line("  <fg=yellow>{$skipped}</> saltados (sin servicios activos).");
            }
            $this->newLine();
            $this->comment('Siguiente paso recomendado: ejecuta el check de renovaciones');
            $this->comment('  php artisan contracts:check-renewals --dry-run');
        }

        $this->reportContractsWithoutEndDate();

        return self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function computeDates($services, Carbon $today): array
    {
        $withDate   = $services->whereNotNull('renewal_date');
        $dates      = $withDate->pluck('renewal_date')->unique()->sort()->values();
        $multiDates = $dates->count() > 1;

        // Próxima fecha de renovación: la más cercana que no haya pasado.
        // Si todas pasaron, usamos la más reciente pasada (el contrato está overdue).
        $upcoming = $withDate->filter(fn ($s) => $s->renewal_date->gte($today))->sortBy('renewal_date');
        $past     = $withDate->filter(fn ($s) => $s->renewal_date->lt($today))->sortByDesc('renewal_date');

        if ($upcoming->isNotEmpty()) {
            $endDate = $upcoming->first()->renewal_date->copy()->startOfDay();
        } elseif ($past->isNotEmpty()) {
            $endDate = $past->first()->renewal_date->copy()->startOfDay();
        } else {
            $endDate = null;
        }

        $startDate = $endDate
            ? $endDate->copy()->subYear()
            : $today->copy()->subYear();

        return [$endDate, $startDate, $multiDates];
    }

    private function computeAmounts($services): array
    {
        $annual  = $services->where('billing_type', 'annual');
        $monthly = $services->where('billing_type', 'monthly');
        $once    = $services->where('billing_type', 'once');

        $annualTotal  = (float) $annual->sum('renewal_amount');
        $monthlyMRR   = (float) $monthly->sum('renewal_amount');
        $onceTotal    = (float) $once->sum('renewal_amount');

        // total_amount = valor anual del contrato (annual + monthly×12 + once)
        $total = round($annualTotal + ($monthlyMRR * 12) + $onceTotal, 2);

        // monthly_amount = MRR real (mensualidades) + referencia anual/12
        $monthlyAmount = round($monthlyMRR + ($annualTotal / 12), 2);

        return [$total, $monthlyAmount];
    }

    private function computeRenewalStatus(?Carbon $endDate, Carbon $today, int $overdueAfter): string
    {
        if (! $endDate) {
            return 'none';
        }
        $diff = (int) $today->diffInDays($endDate, false); // negativo si ya venció
        if ($diff < 0 && abs($diff) >= $overdueAfter) {
            return 'overdue';
        }
        return 'none'; // el cron CheckContractRenewals enviará notificaciones a tiempo
    }

    private function buildNotes($services): string
    {
        $lines = $services->map(function ($s) {
            $date   = $s->renewal_date ? $s->renewal_date->format('Y-m-d') : 'sin fecha';
            $amount = $s->renewal_amount ? number_format((float) $s->renewal_amount, 2) : '0.00';
            return "- {$s->service_name} | vence: {$date} | renovación: \${$amount} | {$s->billing_type}";
        })->implode("\n");

        return "Contrato migrado desde Desglose de Servicios legacy.\n\nServicios incluidos:\n{$lines}";
    }

    private function initContractSeq(): void
    {
        $year = now()->format('Y');
        $last = Contract::whereYear('created_at', $year)
            ->whereNotNull('contract_number')
            ->get(['contract_number'])
            ->map(function ($c) {
                preg_match('/CT-\d{4}-(\d+)/', $c->contract_number ?? '', $m);
                return isset($m[1]) ? (int) $m[1] : 0;
            })
            ->max();

        $this->contractSeq = (int) $last;
    }

    private function nextContractNumber(): string
    {
        $this->contractSeq++;
        return sprintf('CT-%s-%05d', now()->format('Y'), $this->contractSeq);
    }

    private function reportContractsWithoutEndDate(): void
    {
        $count = Contract::whereNull('end_date')->count();
        if ($count > 0) {
            $this->newLine();
            $this->warn("⚠  {$count} contratos existentes no tienen end_date configurada.");
            $this->comment('   El sistema de renovaciones no puede notificarlos hasta que se defina su fecha.');
            $this->comment('   Revísalos en: /contracts (filtro: sin fecha de vencimiento)');
        }
    }
}
