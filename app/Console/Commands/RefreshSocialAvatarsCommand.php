<?php

namespace App\Console\Commands;

use App\Actions\Social\RefreshAccountAvatar;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class RefreshSocialAvatarsCommand extends Command
{
    protected $signature = 'social:refresh-avatars {--client= : Limitar a un cliente}';
    protected $description = 'Vuelve a pedir la foto de perfil de cada cuenta social activa (las URLs de Meta caducan).';

    public function handle(RefreshAccountAvatar $refrescar): int
    {
        $query = SocialAccount::where('status', 'active');
        if ($clientId = $this->option('client')) {
            $query->where('client_id', $clientId);
        }

        $actualizadas = 0;
        foreach ($query->cursor() as $account) {
            if ($refrescar($account)) {
                $actualizadas++;
            }
        }

        $this->info("Avatares actualizados: {$actualizadas}.");

        return self::SUCCESS;
    }
}
