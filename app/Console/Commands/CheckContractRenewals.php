<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Notifications\ContractRenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class CheckContractRenewals extends Command
{
    protected $signature = 'contracts:check-renewals
        {--dry-run : No enviar correos, solo listar lo que se haría}';

    protected $description = 'Revisa contratos próximos a vencer y dispara recordatorios escalonados.';

    public function handle(): int
    {
        $notifyDays = config('quotes.renewals.notify_days_before', [30, 7]);
        $overdueAfter = (int) config('quotes.renewals.mark_overdue_after_days', 7);
        $today = Carbon::today();

        $contracts = Contract::query()
            ->whereNotNull('end_date')
            ->whereNotIn('renewal_status', ['renewed', 'declined'])
            ->whereNull('renewed_contract_id')
            ->get();

        $sent = 0;
        $marked = 0;

        foreach ($contracts as $contract) {
            /** @var \Carbon\Carbon $endDate */
            $endDate = $contract->end_date;
            $diff = (int) $today->diffInDays($endDate, false); // positivo si futuro

            // Vencido: marcar como overdue.
            if ($diff < 0 && abs($diff) >= $overdueAfter && $contract->renewal_status !== 'overdue') {
                if (! $this->option('dry-run')) {
                    $contract->update(['renewal_status' => 'overdue']);
                }
                $this->warn("[overdue] {$contract->contract_number} venció hace " . abs($diff) . " días.");
                $marked++;
                continue;
            }

            // Si no aplica ninguna ventana, skip.
            $applicableDay = collect($notifyDays)
                ->sortDesc()
                ->first(fn ($d) => $diff <= $d && $diff >= 0);

            if ($applicableDay === null) {
                continue;
            }

            $alreadySent = collect($contract->renewal_notifications_sent ?? [])
                ->contains(fn ($entry) => (int) ($entry['day'] ?? -1) === $applicableDay);

            if ($alreadySent) {
                continue;
            }

            // Buscar destinatarios (usuario del cliente).
            $recipients = collect();
            if ($contract->client?->user_id) {
                $user = \App\Models\User::find($contract->client->user_id);
                if ($user) {
                    $recipients->push($user);
                }
            } elseif ($contract->client?->email) {
                $recipients->push((object) ['email' => $contract->client->email, 'routeNotificationFor' => fn () => $contract->client->email]);
            }

            if ($recipients->isEmpty()) {
                $this->warn("[skip] {$contract->contract_number} no tiene destinatarios.");
                continue;
            }

            $this->info("[send] {$contract->contract_number} — ventana de {$applicableDay} días (faltan {$diff}).");

            if (! $this->option('dry-run')) {
                foreach ($recipients as $rec) {
                    if ($rec instanceof \App\Models\User) {
                        $rec->notify(new ContractRenewalReminderNotification($contract, $diff));
                    } else {
                        Notification::route('mail', $rec->email)
                            ->notify(new ContractRenewalReminderNotification($contract, $diff));
                    }
                }

                $history = $contract->renewal_notifications_sent ?? [];
                $history[] = ['day' => $applicableDay, 'sent_at' => now()->toIso8601String()];

                $contract->update([
                    'renewal_status'              => 'notified',
                    'renewal_last_notified_at'    => now(),
                    'renewal_notifications_sent'  => $history,
                ]);
            }

            $sent++;
        }

        $this->newLine();
        $this->info("Recordatorios enviados: {$sent}");
        $this->info("Contratos marcados overdue: {$marked}");

        return self::SUCCESS;
    }
}
