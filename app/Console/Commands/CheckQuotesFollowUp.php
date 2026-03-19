<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteFollowUpNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckQuotesFollowUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotes:check-followup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for quotes created 7 days ago and send follow-up notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7)->toDateString();
        $today = Carbon::now()->toDateString();

        // Buscar cotizaciones pendientes creadas hace exactamente 7 días que no hayan expirado
        $quotes = Quote::where('status', 'Pendiente')
            ->whereDate('created_at', $sevenDaysAgo)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_until')
                      ->orWhereDate('valid_until', '>=', $today);
            })
            ->get();

        if ($quotes->isEmpty()) {
            $this->info('No quotes found for follow-up today.');
            return;
        }

        // Obtener administradores para notificarles
        $admins = User::role(['Administrador', 'Administrador Master'])->get();

        if ($admins->isEmpty()) {
            $this->warn('No admins found to notify.');
            return;
        }

        foreach ($quotes as $quote) {
            Notification::send($admins, new QuoteFollowUpNotification($quote));
        }

        $this->info('Follow-up notifications sent for ' . $quotes->count() . ' quotes.');
    }
}
