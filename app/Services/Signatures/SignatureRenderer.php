<?php

namespace App\Services\Signatures;

use App\Models\EmailSignature;

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

        $values = [
            'name'            => e($signature->name),
            'position'        => e($signature->position ?? ''),
            'email'           => e($signature->email ?? ''),
            'phone'           => e($signature->phone ?? ''),
            'website'         => e($defaults['website'] ?? ''),
            'logo'            => route('email-signatures.image', [$token, 'logo']),
            'photo'           => route('email-signatures.image', [$token, 'photo']),
            'primary_color'   => $this->color($defaults['primary_color'] ?? null, '#264ab3'),
            'secondary_color' => $this->color($defaults['secondary_color'] ?? null, '#666666'),
            'social_links'    => $this->socialLinks($signature->social_links ?? []),
            'banner'          => $this->banner($token),
        ];

        $html = $template->html_content;

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

    private function banner(string $token): string
    {
        return '<div style="margin-top: 12px;"><a href="' . route('email-signatures.banner-link', $token) . '">'
            . '<img src="' . route('email-signatures.image', [$token, 'banner']) . '" alt="" style="display:block; border:0;">'
            . '</a></div>';
    }
}
