<?php

namespace App\Services\Signatures;

use App\Models\Client;
use App\Models\EmailSignature;
use App\Models\SignatureAsset;
use App\Models\SignatureTemplate;

/**
 * Qué plantillas usan las firmas activas de un cliente y cuántas por cada una.
 *
 * Una firma sin plantilla propia cuenta para la de la empresa. La de la
 * empresa aparece aunque todavía no tenga firmas.
 */
class SignatureUsage
{
    public function forClient(Client $client): array
    {
        $counts = EmailSignature::where('client_id', $client->id)
            ->where('is_active', true)
            ->get(['signature_template_id'])
            ->countBy(fn (EmailSignature $s) => $s->signature_template_id ?? $client->signature_template_id ?? 0);

        if ($client->signature_template_id) {
            $counts[$client->signature_template_id] ??= 0;
        }

        $templates = SignatureTemplate::whereIn('id', $counts->keys()->filter())->pluck('name', 'id');

        return $counts
            ->map(fn (int $total, int $templateId) => [
                'template_id' => $templateId ?: null,
                'name'        => $templates[$templateId] ?? null,
                'is_default'  => $templateId && $templateId === (int) $client->signature_template_id,
                'signatures'  => $total,
                'has_logo'    => SignatureAsset::hasLogo($client, $templateId ?: null),
            ])
            ->sortByDesc('is_default')
            ->values()
            ->all();
    }
}
