<?php

namespace App\Support;

/**
 * Calcula impuestos de una cotización a partir del subtotal base
 * (ya con descuento aplicado) y la configuración fiscal pasada como array.
 *
 * Devuelve un array plano listo para persistir en la cotización.
 */
class QuoteTaxCalculator
{
    /**
     * @param  float  $taxableBase  subtotal − descuento
     * @param  array  $config       claves: applies_iva, iva_rate,
     *                              applies_isr_retention, isr_retention_rate,
     *                              applies_iva_retention, iva_retention_rate
     */
    public static function compute(float $taxableBase, array $config): array
    {
        $base = max(0.0, round($taxableBase, 2));

        $appliesIva = (bool) ($config['applies_iva'] ?? false);
        $ivaRate    = (float) ($config['iva_rate']    ?? 0);
        $ivaAmount  = $appliesIva ? round($base * ($ivaRate / 100), 2) : 0.0;

        $appliesIsrR = (bool) ($config['applies_isr_retention'] ?? false);
        $isrRRate    = (float) ($config['isr_retention_rate']    ?? 0);
        $isrRAmount  = $appliesIsrR ? round($base * ($isrRRate / 100), 2) : 0.0;

        $appliesIvaR = (bool) ($config['applies_iva_retention'] ?? false);
        $ivaRRate    = (float) ($config['iva_retention_rate']    ?? 0);
        $ivaRAmount  = $appliesIvaR ? round($base * ($ivaRRate / 100), 2) : 0.0;

        $total = round($base + $ivaAmount - $isrRAmount - $ivaRAmount, 2);

        return [
            'applies_iva'            => $appliesIva,
            'iva_rate'               => $ivaRate,
            'iva_amount'             => $ivaAmount,
            'applies_isr_retention'  => $appliesIsrR,
            'isr_retention_rate'     => $isrRRate,
            'isr_retention_amount'   => $isrRAmount,
            'applies_iva_retention'  => $appliesIvaR,
            'iva_retention_rate'     => $ivaRRate,
            'iva_retention_amount'   => $ivaRAmount,
            'total'                  => max(0.0, $total),
        ];
    }
}
