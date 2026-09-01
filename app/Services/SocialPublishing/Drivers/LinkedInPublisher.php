<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\SocialPostTarget;
use Illuminate\Support\Facades\Storage;

/**
 * LinkedIn UGC Posts API (v2)
 * Doc: https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api
 *
 * El formato lo elige quien compone el post (`linkedin_type`). Antes todo salía
 * como texto plano: elegir "Post con imagen" publicaba el texto sin la imagen,
 * sin ningún error que lo delatara.
 */
class LinkedInPublisher extends AbstractPublisher
{
    protected function doPublish(SocialPostTarget $target): array
    {
        $account  = $target->account;
        $post     = $target->post;
        $token    = $account->access_token;
        $authorId = $account->meta['urn'] ?? "urn:li:person:{$account->provider_user_id}";

        $contenido = match ($post->options['linkedin_type'] ?? 'text') {
            'image'   => $this->contenidoConImagen($target, $token, $authorId),
            'article' => $this->contenidoDeArticulo($post),
            default   => ['shareMediaCategory' => 'NONE'],
        };

        $body = [
            'author'         => $authorId,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $post->body ?? ''],
                    ...$contenido,
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ];

        $resp = $this->http()
            ->withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/ugcPosts', $body)
            ->throw();

        $id = $resp->json('id') ?? $resp->header('X-RestLi-Id');

        return [
            'id'  => $id,
            'url' => $id ? "https://www.linkedin.com/feed/update/{$id}" : null,
        ];
    }

    /**
     * Sube la imagen y devuelve la referencia para el post.
     *
     * LinkedIn no acepta la imagen dentro del post ni por URL: hay que
     * registrar la subida, mandar los bytes a la URL que devuelve y recién
     * entonces citar el asset. Tres llamadas antes de publicar.
     */
    private function contenidoConImagen(SocialPostTarget $target, string $token, string $authorId): array
    {
        $imagen = $this->primeraImagen($target);
        if (!$imagen) {
            throw new \RuntimeException('LinkedIn: el post con imagen requiere una imagen adjunta.');
        }

        $ruta = $imagen['path'];
        if (!Storage::disk('public')->exists($ruta)) {
            throw new \RuntimeException('Archivo de imagen no encontrado en disco.');
        }

        $registro = $this->http()
            ->withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'owner'   => $authorId,
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'serviceRelationships' => [[
                        'relationshipType' => 'OWNER',
                        'identifier'       => 'urn:li:userGeneratedContent',
                    ]],
                ],
            ])
            ->throw()
            ->json();

        // La clave del mecanismo de subida lleva puntos en el nombre, así que
        // no se puede leer con data_get().
        $mecanismo = $registro['value']['uploadMechanism']
            ['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'] ?? [];

        $urlDeSubida = $mecanismo['uploadUrl'] ?? null;
        $asset       = $registro['value']['asset'] ?? null;

        if (!$urlDeSubida || !$asset) {
            throw new \RuntimeException('LinkedIn no devolvió la URL de subida de la imagen.');
        }

        $this->http()
            ->withToken($token)
            ->withBody(Storage::disk('public')->get($ruta), $imagen['mime'] ?? 'image/jpeg')
            ->post($urlDeSubida)
            ->throw();

        $alt = $target->post->options['linkedin_alt_text'] ?? null;

        return [
            'shareMediaCategory' => 'IMAGE',
            'media' => [array_filter([
                'status'      => 'READY',
                'media'       => $asset,
                'description' => $alt ? ['text' => $alt] : null,
            ])],
        ];
    }

    private function contenidoDeArticulo($post): array
    {
        $url = $post->options['linkedin_article_url'] ?? null;
        if (!$url) {
            throw new \RuntimeException('LinkedIn: el artículo requiere la URL que se va a compartir.');
        }

        return [
            'shareMediaCategory' => 'ARTICLE',
            'media' => [array_filter([
                'status'      => 'READY',
                'originalUrl' => $url,
                'title'       => $post->title ? ['text' => $post->title] : null,
            ])],
        ];
    }

    private function primeraImagen(SocialPostTarget $target): ?array
    {
        foreach ($target->post->mediaPrincipal() as $m) {
            if (is_array($m) && str_starts_with((string) ($m['mime'] ?? ''), 'image/')) {
                return $m;
            }
        }

        return null;
    }

    public function fetchAvatarUrl(\App\Models\SocialAccount $account): ?string
    {
        $resp = $this->http()
            ->withToken($account->access_token)
            ->get('https://api.linkedin.com/v2/userinfo')
            ->throw()
            ->json();

        return $resp['picture'] ?? null;
    }
}
