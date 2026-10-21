<?php

namespace App\Services\Signatures;

use App\Models\EmailSignature;
use App\Models\SignatureAsset;
use App\Support\SignatureUrls;

/**
 * Arma el HTML de la firma de un empleado.
 *
 * Las imágenes (logo, foto, banner) NUNCA se incrustan con su ruta real: van
 * a URLs fijas por token que resuelven el archivo vigente al momento de abrir
 * el correo. Es lo único de una firma pegada en Outlook/Gmail que se puede
 * actualizar después; el texto queda congelado en el cliente de correo.
 */
class SignatureRenderer
{
    private const SOCIAL_ICONS = [
        'facebook'  => 'https://cdn-icons-png.flaticon.com/32/733/733547.png',
        'instagram' => 'https://cdn-icons-png.flaticon.com/32/2111/2111463.png',
        'linkedin'  => 'https://cdn-icons-png.flaticon.com/32/3536/3536505.png',
        'twitter'   => 'https://cdn-icons-png.flaticon.com/32/3256/3256609.png',
    ];

    public function render(EmailSignature $signature): string
    {
        $template = $signature->effectiveTemplate();

        if (! $template) {
            return '';
        }

        $defaults = $signature->client?->signature_defaults ?? [];
        $token = $signature->token;
        $v = ['v' => $this->imageVersion($signature)];

        $values = [
            'name'            => e($signature->name),
            'position'        => e($signature->position ?? ''),
            'email'           => e($signature->email ?? ''),
            'phone'           => e($signature->phone ?? ''),
            'website'         => e($defaults['website'] ?? ''),
            'logo'            => SignatureUrls::route('email-signatures.image', [$token, 'logo', ...$v]),
            'photo'           => SignatureUrls::route('email-signatures.image', [$token, 'photo', ...$v]),
            'primary_color'   => $this->color($defaults['primary_color'] ?? null, '#264ab3'),
            'secondary_color' => $this->color($defaults['secondary_color'] ?? null, '#666666'),
            'social_links'    => $this->socialLinks($signature->social_links ?? []),
            'banner'          => $this->banner($token, $v),
        ];

        $html = $template->html_content;

        // Sin foto se quita la etiqueta completa: un hueco en blanco o un
        // avatar genérico se ven peor que no tener foto.
        if (! $signature->photo_path) {
            $html = preg_replace('/<img\b[^>]*\{\{photo\}\}[^>]*>/i', '', $html);
        }

        // El banner va siempre, aunque hoy no haya ninguno: si se sube uno
        // después, aparece en las firmas ya instaladas. Mientras no hay, la URL
        // sirve un pixel transparente.
        if (! str_contains($html, '{{banner}}')) {
            $html .= '{{banner}}';
        }

        foreach ($values as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }

        return $this->absolutizeUrls($html);
    }

    /**
     * `src="/img/tel.png"` carga en el admin pero no en Outlook/Gmail, que no
     * saben de qué dominio viene la firma pegada.
     */
    private function absolutizeUrls(string $html): string
    {
        return preg_replace_callback(
            '#\b(src|href)=(["\'])(/(?!/)[^"\']*)\2#i',
            fn ($m) => $m[1] . '=' . $m[2] . url($m[3]) . $m[2],
            $html
        );
    }

    private function color(?string $value, string $fallback): string
    {
        return $value && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    }

    private function socialLinks(array $links): string
    {
        $html = '';

        foreach (self::SOCIAL_ICONS as $network => $icon) {
            $url = $links[$network] ?? null;

            if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $html .= '<a href="' . e($url) . '" style="margin-right: 8px;"><img src="' . $icon . '" width="20" height="20" style="display:inline-block" alt="' . $network . '"></a>';
        }

        return $html === '' ? '' : '<div style="margin-top: 10px;">' . $html . '</div>';
    }

    /**
     * Cambia cuando cambia alguna imagen de la firma (foto o logos/banners de
     * la empresa). El servidor la ignora; solo sirve para que el navegador no
     * muestre la imagen anterior desde su caché en la vista previa. Las firmas
     * ya pegadas conservan su `v` viejo y siguen recibiendo la imagen vigente.
     */
    private function imageVersion(EmailSignature $signature): string
    {
        $assets = SignatureAsset::where('client_id', $signature->client_id)
            ->selectRaw('count(*) as total, max(updated_at) as last')
            ->first();

        return substr(md5(implode('|', [
            $signature->photo_path,
            // El logo depende de la plantilla: cambiarla debe cambiar la URL.
            $signature->effectiveTemplate()?->id,
            $assets?->total,
            $assets?->last,
            now()->toDateString(), // los de temporada cambian por fecha
        ])), 0, 8);
    }

    private function banner(string $token, array $v): string
    {
        return '<div style="margin-top: 12px;"><a href="' . SignatureUrls::route('email-signatures.banner-link', [$token]) . '">'
            . '<img src="' . SignatureUrls::route('email-signatures.image', [$token, 'banner', ...$v]) . '" alt="" style="display:block; border:0;">'
            . '</a></div>';
    }
}
