<?php

namespace App\Support\Money;

use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio central de moneda. ÚNICA fuente de verdad para:
 *  - Validar códigos de moneda soportados.
 *  - Resolver tipos de cambio (con cache + fallback al día hábil anterior).
 *  - Convertir importes con redondeo HALF_UP a los decimales de la moneda destino.
 *  - Formatear importes para display (PHP/BCMath; el frontend tiene su gemelo en
 *    resources/js/Composables/useMoney.js).
 *
 * Toda operación monetaria del dominio (Quote/Contract/Payment/Invoice/Report)
 * debe pasar por aquí. NO sumes amounts de monedas distintas en otro lugar.
 */
class CurrencyService
{
    /**
     * Códigos ISO 4217 soportados (cerrado por config).
     *
     * @return array<int,string>
     */
    public function codes(): array
    {
        return array_keys(config('currencies.supported', []));
    }

    public function base(): string
    {
        return strtoupper((string) config('currencies.base', 'MXN'));
    }

    public function default(): string
    {
        return strtoupper((string) config('currencies.default', $this->base()));
    }

    public function meta(string $code): array
    {
        $code = $this->normalize($code);
        $meta = config("currencies.supported.{$code}");
        if (! $meta) {
            throw new \InvalidArgumentException("Moneda no soportada: {$code}");
        }
        return $meta;
    }

    public function assertSupported(string $code): string
    {
        $code = $this->normalize($code);
        if (! in_array($code, $this->codes(), true)) {
            throw new \InvalidArgumentException("Moneda no soportada: {$code}");
        }
        return $code;
    }

    public function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /**
     * Tipo de cambio FROM → TO a una fecha dada (por defecto, hoy).
     *
     * Resolución:
     *  1) Si from === to, retorna 1.
     *  2) Busca exacto en `exchange_rates` (cualquier fuente, prefiriendo
     *     manual > banxico para permitir overrides; el más reciente gana).
     *  3) Si no existe esa fecha, retrocede hasta 14 días buscando la última
     *     tasa publicada (cubre fines de semana y festivos).
     *  4) Inversa: si solo existe TO→FROM, retorna 1 / rate.
     *  5) Triangulación vía base si los dos extremos están guardados contra
     *     la moneda base (ej. USD→EUR vía MXN).
     *  6) Si todo falla, lanza ExchangeRateNotFoundException.
     *
     * @return string Tasa con precisión configurada (string para evitar floats).
     */
    public function rate(string $from, string $to, CarbonInterface|string|null $date = null): string
    {
        $from = $this->normalize($from);
        $to   = $this->normalize($to);

        if ($from === $to) {
            return '1.00000000';
        }

        $on = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date ?: now())->startOfDay();
        $key = "fx:{$from}:{$to}:{$on->toDateString()}";

        return Cache::remember($key, now()->addHours(6), function () use ($from, $to, $on) {
            // 1) Directo
            $rate = $this->lookup($from, $to, $on);
            if ($rate !== null) {
                return $rate;
            }
            // 2) Inverso
            $inverse = $this->lookup($to, $from, $on);
            if ($inverse !== null && (float) $inverse > 0) {
                return $this->formatRate(bcdiv('1', $inverse, 12));
            }
            // 3) Triangulación vía base
            $base = $this->base();
            if ($from !== $base && $to !== $base) {
                $fb = $this->lookup($from, $base, $on) ?? (
                    ($inv = $this->lookup($base, $from, $on)) && (float) $inv > 0 ? bcdiv('1', $inv, 12) : null
                );
                $bt = $this->lookup($base, $to, $on) ?? (
                    ($inv2 = $this->lookup($to, $base, $on)) && (float) $inv2 > 0 ? bcdiv('1', $inv2, 12) : null
                );
                if ($fb !== null && $bt !== null) {
                    return $this->formatRate(bcmul($fb, $bt, 12));
                }
            }

            throw new ExchangeRateNotFoundException(
                "No hay tipo de cambio disponible para {$from}→{$to} al {$on->toDateString()}."
            );
        });
    }

    /**
     * Convierte un importe a la moneda destino, snapshot a fecha.
     * Devuelve string para preservar precisión; usa bccomp/bcadd para sumar.
     */
    public function convert(string|float|int $amount, string $from, string $to, CarbonInterface|string|null $date = null): string
    {
        $from = $this->normalize($from);
        $to   = $this->normalize($to);
        $decimals = (int) ($this->meta($to)['decimals'] ?? 2);

        if ($from === $to) {
            return number_format((float) $amount, $decimals, '.', '');
        }

        $rate = $this->rate($from, $to, $date);
        $raw  = bcmul((string) $amount, $rate, 12);

        // Redondeo HALF_UP manual (bcmath trunca por defecto).
        return $this->roundHalfUp($raw, $decimals);
    }

    /**
     * Formatea un importe para display server-side. Para Vue usar useMoney().
     */
    public function format(string|float|int $amount, ?string $currency = null, ?string $locale = null): string
    {
        $code = $this->assertSupported($currency ?: $this->default());
        $meta = $this->meta($code);
        $loc  = $locale ?: ($meta['locale'] ?? 'es-MX');

        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter($loc, \NumberFormatter::CURRENCY);
            return $fmt->formatCurrency((float) $amount, $code);
        }
        return ($meta['symbol'] ?? '$') . number_format((float) $amount, (int) ($meta['decimals'] ?? 2)) . ' ' . $code;
    }

    /**
     * Devuelve el snapshot recomendado para guardar en un documento nuevo.
     * Si la moneda iguala a la base, devuelve 1.
     */
    public function snapshotRate(string $currency, CarbonInterface|string|null $date = null): string
    {
        $currency = $this->assertSupported($currency);
        if ($currency === $this->base()) {
            return '1.00000000';
        }
        return $this->rate($currency, $this->base(), $date);
    }

    // ---------------------- internos ----------------------

    /** @return string|null Tasa cruda como string o null si no se encuentra. */
    private function lookup(string $from, string $to, CarbonInterface $on): ?string
    {
        $row = ExchangeRate::query()
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('rate_date', '<=', $on->toDateString())
            ->whereDate('rate_date', '>=', $on->copy()->subDays(14)->toDateString())
            ->orderByDesc('rate_date')
            ->orderByRaw("CASE WHEN source = 'manual' THEN 0 ELSE 1 END")
            ->first(['rate']);

        return $row ? (string) $row->rate : null;
    }

    private function formatRate(string $value): string
    {
        return $this->roundHalfUp($value, (int) config('currencies.rate_decimals', 8));
    }

    private function roundHalfUp(string $value, int $decimals): string
    {
        // bcmath no implementa HALF_UP. Sumamos 0.5*10^-decimals y truncamos.
        $isNegative = bccomp($value, '0', 16) === -1;
        $abs = ltrim($value, '-');
        $bump = '0.' . str_repeat('0', $decimals) . '5';
        $bumped = bcadd($abs, $bump, $decimals + 1);
        $rounded = bcadd($bumped, '0', $decimals);
        return $isNegative ? ('-' . $rounded) : $rounded;
    }
}
