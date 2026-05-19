<?php

namespace App\Services\Fx;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Banxico SIE API.
 *
 * Por defecto descarga la serie USD→MXN FIX (publicada por DOF al día hábil
 * siguiente). Si se requieren más pares, agregar la serie en
 * config/currencies.php y mapearlas en `pairs()`.
 *
 * Doc: https://www.banxico.org.mx/SieAPIRest/service/v1/doc/consultaActualizaciones
 */
class BanxicoFxFetcher
{
    /** Devuelve mapa series→[from,to] desde config. */
    public function pairs(): array
    {
        $series = config('currencies.banxico.series', []);
        $pairs = [];
        foreach ($series as $key => $serieId) {
            [$from, $to] = explode('_', $key);
            $pairs[$serieId] = ['from' => $from, 'to' => $to];
        }
        return $pairs;
    }

    /**
     * Trae los últimos tipos de cambio para todos los pares configurados y los
     * persiste (idempotente vía unique key).
     *
     * @param  Carbon|null $from Fecha inicial (default: hoy-7).
     * @param  Carbon|null $to   Fecha final   (default: hoy).
     * @return int Filas insertadas/actualizadas.
     */
    public function sync(?Carbon $from = null, ?Carbon $to = null): int
    {
        $token = config('currencies.banxico.token');
        if (! $token) {
            throw new \RuntimeException('BANXICO_TOKEN no configurado en .env');
        }

        $from = ($from ?: now()->subDays(7))->startOfDay();
        $to   = ($to ?: now())->endOfDay();
        $count = 0;

        foreach ($this->pairs() as $serieId => $pair) {
            $url = rtrim((string) config('currencies.banxico.endpoint'), '/')
                . "/{$serieId}/datos/{$from->format('Y-m-d')}/{$to->format('Y-m-d')}";

            $response = Http::timeout((int) config('currencies.banxico.timeout', 15))
                ->withHeaders(['Bmx-Token' => $token])
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Banxico fetch failed', [
                    'serie'  => $serieId,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                continue;
            }

            $data = data_get($response->json(), 'bmx.series.0.datos', []);
            foreach ($data as $point) {
                $rate = str_replace(',', '', (string) ($point['dato'] ?? ''));
                if (! is_numeric($rate)) {
                    continue;
                }
                ExchangeRate::updateOrCreate(
                    [
                        'rate_date'     => Carbon::createFromFormat('d/m/Y', $point['fecha']),
                        'from_currency' => $pair['from'],
                        'to_currency'   => $pair['to'],
                        'source'        => 'banxico',
                    ],
                    ['rate' => $rate]
                );
                $count++;
            }
        }

        return $count;
    }
}
