<?php

namespace App\Console\Commands;

use App\Actions\Social\FetchAccountStats;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class FetchSocialAccountStatsCommand extends Command
{
    protected $signature = 'social:fetch-account-stats {--client= : Limitar a un cliente}';
    protected $description = 'Sincroniza estadísticas diarias (followers, alcance) de cada cuenta social activa.';

    public function handle(FetchAccountStats $fetch): int
    {
        $query = SocialAccount::where('status', 'active');
        if ($clientId = $this->option('client')) {
            $query->where('client_id', $clientId);
        }

        $count = 0;
        foreach ($query->cursor() as $account) {
            if ($fetch($account)) {
                $count++;
            }
        }

        $this->info("Cuentas sincronizadas: {$count}.");
        return self::SUCCESS;
    }
}
