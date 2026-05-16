<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Support\QuoteStateMachine;
use Illuminate\Console\Command;

class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Marca como Expirada toda cotización Enviada cuya valid_until ya pasó.';

    public function handle(): int
    {
        $cutoff = now()->startOfDay();

        $quotes = Quote::where('status', 'Enviada')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($quotes as $quote) {
            if (QuoteStateMachine::canTransition($quote->status, 'Expirada')) {
                QuoteStateMachine::transition($quote, 'Expirada');
                $count++;
            }
        }

        $this->info("Cotizaciones expiradas: {$count}");
        return self::SUCCESS;
    }
}
