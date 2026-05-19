<?php

namespace App\Console\Commands;

use App\Services\Fx\BanxicoFxFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'fx:fetch
                            {--from= : Fecha inicial Y-m-d (default: hoy-7)}
                            {--to=   : Fecha final Y-m-d (default: hoy)}';

    protected $description = 'Sincroniza los tipos de cambio desde Banxico (DOF FIX).';

    public function handle(BanxicoFxFetcher $fetcher): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to   = $this->option('to')   ? Carbon::parse($this->option('to'))   : null;

        try {
            $count = $fetcher->sync($from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("OK · {$count} registros sincronizados.");
        return self::SUCCESS;
    }
}
