<?php

namespace App\Services\SocialPublishing;

use App\Models\SocialAccount;
use App\Models\SocialPostTarget;

interface Publisher
{
    /**
     * Publica el target en la red social. Debe llenar:
     *   - $target->platform_post_id
     *   - $target->platform_url
     *   - $target->status (published|failed)
     *   - $target->error_message (si falla)
     * y devolver el SocialPostTarget actualizado.
     */
    public function publish(SocialPostTarget $target): SocialPostTarget;

    /**
     * Obtiene métricas (insights) de un post ya publicado.
     * Devuelve un array con keys normalizadas:
     *   impressions, reach, likes, comments, shares, saves, clicks,
     *   video_views, engagement_rate, raw
     * Cualquier key faltante se asume 0.
     */
    public function fetchInsights(SocialPostTarget $target): array;

    /**
     * Estadísticas a nivel cuenta (followers, alcance, etc.).
     * Devuelve un array con keys normalizadas:
     *   followers, following, posts_count, profile_views,
     *   page_impressions, page_reach, raw
     */
    public function fetchAccountStats(SocialAccount $account): array;

    /**
     * URL actual de la foto de perfil de la cuenta.
     *
     * Hay que volver a pedirla cada tanto: Meta firma las suyas y caducan a
     * las pocas horas, así que la que se guardó al conectar la cuenta acaba
     * devolviendo 403. Devuelve null si el provider no la expone o si la
     * consulta falla.
     */
    public function fetchAvatarUrl(SocialAccount $account): ?string;
}

