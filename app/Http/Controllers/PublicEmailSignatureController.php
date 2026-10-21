<?php

namespace App\Http\Controllers;

use App\Models\EmailSignature;
use App\Models\SignatureAsset;
use App\Services\Signatures\SignatureRenderer;
use App\Support\SignatureUrls;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Lo que ve el empleado (sin login) y lo que cargan los correos.
 *
 * Las imágenes se sirven directo —sin redirección cuando el archivo es
 * nuestro— y con caché corta: algunos clientes de correo y proxies (Gmail)
 * guardan copia, y una caché larga retrasaría el cambio de logo. El pixel de
 * "no hay imagen" no se cachea nunca: si se guardara, el logo recién subido
 * seguiría sin verse hasta que venciera.
 */
class PublicEmailSignatureController extends Controller
{
    // PNG transparente de 1x1.
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function show(string $token, SignatureRenderer $renderer)
    {
        $signature = $this->activeSignature($token);
        abort_unless($signature, 404);

        $signature->forceFill(['last_viewed_at' => now()])->saveQuietly();

        return Inertia::render('EmailSignatures/PublicShow', [
            'name'    => $signature->name,
            'company' => $signature->client?->business_name,
            'html'    => $renderer->render($signature),
        ]);
    }

    public function image(string $token, string $slot)
    {
        // Lo que toca servir se guarda unos minutos: un envío masivo pide la
        // misma imagen miles de veces y no hace falta ir a la base cada vez.
        // Cualquier cambio del admin invalida esto (ver SignatureUrls::flushCache).
        $resolved = SignatureUrls::cache()->remember(
            SignatureUrls::cacheKey("img:{$token}:{$slot}:" . now()->toDateString()),
            config('signatures.image_cache_seconds'),
            fn () => $this->resolveImage($token, $slot)
        );

        return match ($resolved['type']) {
            'file'     => Storage::disk('public')->exists($resolved['path']) ? $this->file($resolved['path']) : $this->pixel(),
            'redirect' => redirect()->away($resolved['url']),
            default    => $this->pixel(),
        };
    }

    /** @return array{type: 'file'|'redirect'|'pixel', path?: string, url?: string} */
    private function resolveImage(string $token, string $slot): array
    {
        $signature = $this->activeSignature($token);

        // Una firma revocada deja de mostrar imágenes, pero sin ícono roto en
        // los correos que ya se enviaron.
        if (! $signature) {
            return ['type' => 'pixel'];
        }

        if ($slot === 'photo') {
            // Sin foto (o se quitó): las firmas ya pegadas dejan de mostrarla.
            return $signature->photo_path
                ? ['type' => 'file', 'path' => $signature->photo_path]
                : ['type' => 'pixel'];
        }

        $asset = SignatureAsset::current($signature->client_id, $slot, $signature->effectiveTemplate()?->id);

        if ($asset) {
            return ['type' => 'file', 'path' => $asset->file_path];
        }

        // Sin logo subido todavía: el que se configuró en el expediente del cliente.
        $fallback = $slot === 'logo' ? SignatureAsset::fallbackLogoUrl($signature->client) : null;

        return $fallback ? ['type' => 'redirect', 'url' => $fallback] : ['type' => 'pixel'];
    }

    public function bannerLink(string $token)
    {
        $signature = $this->activeSignature($token);
        abort_unless($signature, 404);

        $url = SignatureAsset::current($signature->client_id, 'banner', $signature->effectiveTemplate()?->id)?->link_url
            ?? $signature->client?->signature_defaults['website'] ?? null;

        abort_unless($url, 404);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return redirect()->away($url);
    }

    private function activeSignature(string $token): ?EmailSignature
    {
        return EmailSignature::with('client.signatureTemplate', 'signatureTemplate')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();
    }

    private function file(string $path)
    {
        return Storage::disk('public')->response($path, null, $this->cacheHeaders());
    }

    private function pixel()
    {
        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function cacheHeaders(): array
    {
        return ['Cache-Control' => 'public, max-age=' . config('signatures.image_cache_seconds')];
    }
}
