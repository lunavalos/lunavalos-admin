<?php

namespace App\Actions\Social;

use App\Models\SocialAccount;
use App\Services\SocialPublishing\PublisherRegistry;

/**
 * Vuelve a pedir la foto de perfil de una cuenta.
 *
 * Las URLs de Meta (`profile_picture_url` de Instagram, la foto de una página
 * de Facebook) van firmadas y caducan a las pocas horas: la que se guardó al
 * conectar la cuenta acaba devolviendo 403 y en la interfaz quedaba el ícono
 * de imagen rota. El respaldo del avatar tapa el hueco; esto devuelve la foto.
 */
class RefreshAccountAvatar
{
    public function __construct(private PublisherRegistry $registry) {}

    public function __invoke(SocialAccount $account): bool
    {
        if ($account->status !== 'active') {
            return false;
        }

        try {
            $url = $this->registry->for($account)->fetchAvatarUrl($account);
        } catch (\Throwable $e) {
            // Un token vencido o un provider caído no debe cortar el recorrido
            // de las demás cuentas.
            report($e);

            return false;
        }

        if (!$url || $url === $account->avatar_url) {
            return false;
        }

        $account->update(['avatar_url' => $url]);

        return true;
    }
}
