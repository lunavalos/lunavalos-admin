<?php

namespace App\Services\SocialPublishing;

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
}
