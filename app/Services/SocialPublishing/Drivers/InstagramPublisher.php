<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;

/**
 * Instagram Graph API — requiere cuenta IG Business vinculada a una página de Facebook.
 * Flujo: 1) crear media container, 2) publicar container.
 * Las imágenes/videos DEBEN estar accesibles públicamente por HTTPS.
 */
class InstagramPublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account = $target->account;
        $post    = $target->post;
        $igId    = $account->meta['ig_business_id'] ?? null;
        // access_token ya es el page token (lo guarda así handleInstagram).
        $token   = $account->access_token;
        $version = config('services.facebook.graph_version', 'v19.0');

        if (!$igId) {
            throw new \RuntimeException('IG Business account no configurada. Reconecta la cuenta.');
        }

        $media = $this->mediaUrls($target);
        if (empty($media)) {
            throw new \RuntimeException('Instagram requiere al menos una imagen o video.');
        }

        // 1) Crear contenedor (single image, flujo más simple)
        $containerResp = $this->http()->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igId}/media",
            [
                'image_url'    => $media[0],
                'caption'      => $post->body,
                'access_token' => $token,
            ]
        )->throw()->json();

        $creationId = $containerResp['id'] ?? null;
        if (!$creationId) {
            throw new \RuntimeException('No se pudo crear el media container de Instagram.');
        }

        // 2) Publicar contenedor
        $publishResp = $this->http()->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igId}/media_publish",
            [
                'creation_id'  => $creationId,
                'access_token' => $token,
            ]
        )->throw()->json();

        $mediaId = $publishResp['id'] ?? null;

        return [
            'id'  => $mediaId,
            'url' => $mediaId ? "https://www.instagram.com/p/{$mediaId}/" : null,
        ];
    }
}
