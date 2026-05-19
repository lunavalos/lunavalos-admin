<?php

/**
 * Configuración del módulo de cotizaciones / contratos.
 *
 * Estos valores son los defaults; los settings de DB (tabla `settings`)
 * pueden sobreescribirlos vía `quotes.down_payment_percent`, etc.
 */

return [

    // Porcentaje sugerido de anticipo al convertir cotización -> contrato.
    'down_payment_percent' => env('QUOTES_DOWN_PAYMENT_PERCENT', 50),

    // Vigencia (en días) por defecto de una cotización enviada.
    'default_validity_days' => env('QUOTES_DEFAULT_VALIDITY_DAYS', 15),

    // Máximo de meses que se permite dividir el pago del paquete (1..n).
    'max_payment_plan_months' => 24,

    // Estados permitidos del workflow de cotización.
    'statuses' => [
        'Borrador',
        'Enviada',
        'Aceptada',
        'Rechazada',
        'Expirada',
        'Convertida',
        'Requiere ajustes',
    ],

    // Transiciones permitidas. Convertida y Rechazada son terminales.
    'transitions' => [
        'Borrador'          => ['Enviada', 'Rechazada'],
        'Enviada'           => ['Aceptada', 'Rechazada', 'Expirada', 'Requiere ajustes'],
        'Requiere ajustes'  => ['Enviada', 'Rechazada'],
        'Aceptada'          => ['Convertida', 'Rechazada', 'Expirada'],
        'Expirada'          => ['Enviada'],
        'Rechazada'         => [],
        'Convertida'        => [],
    ],

    // Renovaciones automáticas: configuración de avisos.
    'renewals' => [
        // Días antes de end_date para empezar a notificar (escalonados).
        'notify_days_before' => [60, 30, 15, 7, 1],
        // Si ya pasó N días desde end_date sin renovar, marcar como vencido.
        'mark_overdue_after_days' => 7,
        // Correo de copia para el equipo administrativo (opcional).
        'admin_cc' => env('CONTRACTS_RENEWAL_CC'),
        // Horizonte de años hacia adelante para generar cuotas de renovación
        // (anualidades + items con precio de renovación). El cliente puede
        // cancelar en cualquier momento; estas cuotas se programan para que
        // cobranza pueda solicitar los pagos a futuro.
        'forecast_years' => (int) env('CONTRACTS_RENEWAL_FORECAST_YEARS', 10),
    ],

];
