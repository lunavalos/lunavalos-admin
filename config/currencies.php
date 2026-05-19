<?php

/**
 * Configuración central de divisas para todo el dominio financiero.
 *
 * Conceptos clave:
 * - "base": moneda funcional/contable del negocio. TODOS los reportes consolidados
 *   se expresan en esta moneda usando la tasa snapshot guardada por documento.
 * - "supported": lista cerrada de monedas operables. Cualquier intento de operar
 *   con una moneda fuera de esta lista debe fallar (defensa contra inputs raros).
 * - "fx_source": fuente preferida para tipos de cambio históricos/actuales.
 * - "rounding": precisión decimal y modo de redondeo para conversiones.
 *
 * Decisiones contables:
 * - Todo documento monetario emitido (Quote, Contract, ClientService, Invoice,
 *   ClientPayment, ClientCost) guarda SIEMPRE su `currency` + `exchange_rate`
 *   (tasa snapshot al momento de fijarse el importe). Esto evita reescribir
 *   históricos cuando cambian las cotizaciones.
 * - Las sumatorias entre monedas distintas SOLO se hacen normalizando a `base`
 *   con el `exchange_rate` snapshot de cada renglón.
 * - El CFDI se emite siempre en `cfdi_currency` (MXN, restricción SAT vigente
 *   para este proyecto). Los pagos en divisa extranjera se convierten al
 *   momento del timbrado.
 */

return [

    'base'      => env('CURRENCY_BASE', 'MXN'),
    'default'   => env('CURRENCY_DEFAULT', 'MXN'),

    /**
     * Lista de monedas habilitadas en la UI y la API.
     * Estructura: code => metadata.
     */
    'supported' => [
        'MXN' => [
            'code'           => 'MXN',
            'name'           => 'Peso mexicano',
            'symbol'         => '$',
            'locale'         => 'es-MX',
            'decimals'       => 2,
            'sat_code'       => 'MXN', // c_Moneda
        ],
        'USD' => [
            'code'           => 'USD',
            'name'           => 'Dólar estadounidense',
            'symbol'         => 'US$',
            'locale'         => 'en-US',
            'decimals'       => 2,
            'sat_code'       => 'USD',
        ],
    ],

    /**
     * Fuente predeterminada de tipos de cambio.
     * Soportados: 'banxico' (DOF FIX), 'manual'.
     */
    'fx_source' => env('CURRENCY_FX_SOURCE', 'banxico'),

    /**
     * Serie Banxico FIX (USD→MXN, día hábil siguiente para SAT).
     * Doc: https://www.banxico.org.mx/SieAPIRest/
     */
    'banxico' => [
        'token'      => env('BANXICO_TOKEN'),
        'series'     => [
            // USD→MXN (FIX/DOF)
            'USD_MXN' => env('BANXICO_SERIES_USD_MXN', 'SF43718'),
        ],
        'endpoint'   => 'https://www.banxico.org.mx/SieAPIRest/service/v1/series',
        'timeout'    => 15,
    ],

    /**
     * Precisión interna para almacenar tasas (decimal:8 en BD).
     * El redondeo de IMPORTES (monto convertido) siempre es a `decimals` de la
     * moneda destino con HALF_UP, igual que en NIF/IFRS.
     */
    'rate_decimals' => 8,

    /**
     * CFDI/SAT: por política del proyecto, los CFDIs se emiten siempre en MXN.
     * Si un pago entra en USD, se convierte a MXN al momento de timbrar usando
     * el tipo de cambio snapshot guardado en el pago (o la tasa del día si no
     * existiera).
     */
    'cfdi_currency' => 'MXN',
];
