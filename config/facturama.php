<?php

/**
 * Integración con Facturama (PAC) para timbrado de CFDI 4.0.
 *
 * Modos:
 *  - 'sandbox'    -> https://apisandbox.facturama.mx/  (cuentas de prueba)
 *  - 'production' -> https://api.facturama.mx/
 *
 * Credenciales: api_user / api_password de Facturama (HTTP Basic).
 */

return [

    'mode' => env('FACTURAMA_MODE', 'sandbox'),

    'base_url' => env('FACTURAMA_MODE', 'sandbox') === 'production'
        ? 'https://api.facturama.mx/'
        : 'https://apisandbox.facturama.mx/',

    'api_user'     => env('FACTURAMA_API_USER'),
    'api_password' => env('FACTURAMA_API_PASSWORD'),

    // Datos del emisor para snapshot del CFDI (override desde Settings si aplica).
    'issuer' => [
        'rfc'              => env('FACTURAMA_ISSUER_RFC'),
        'name'             => env('FACTURAMA_ISSUER_NAME'),
        'fiscal_regime'    => env('FACTURAMA_ISSUER_REGIME', '601'),
        'tax_zip_code'     => env('FACTURAMA_ISSUER_ZIP'),
    ],

    // Defaults para el item CFDI cuando no se pueda inferir del servicio.
    'defaults' => [
        // ClaveProdServ (catálogo SAT c_ClaveProdServ) → 84111506 = "Servicios de facturación".
        'product_code' => env('FACTURAMA_DEFAULT_PRODUCT_CODE', '84111506'),
        // ClaveUnidad   (catálogo SAT c_ClaveUnidad)    → E48 = "Unidad de servicio".
        'unit_code'    => env('FACTURAMA_DEFAULT_UNIT_CODE', 'E48'),
        'unit'         => env('FACTURAMA_DEFAULT_UNIT', 'Servicio'),
        'currency'     => 'MXN',
        'payment_form_default' => '03', // 03=Transferencia
    ],

    // Timeout HTTP en segundos.
    'timeout' => (int) env('FACTURAMA_TIMEOUT', 30),
];
