<?php

/**
 * Configuración del módulo de Service Addons.
 *
 * - categories: lista fija de categorías que puede tener un Service Addon.
 *   Esta misma lista es la que se ofrece como "required_addon_category" en
 *   los servicios principales (paquetes), para obligar al cliente a elegir
 *   un addon de esa categoría al momento de cotizar.
 *
 * - billing_cycles: ciclos de facturación soportados. `custom_months` permite
 *   definir un valor numérico de meses en `billing_cycle_months` del addon.
 */

return [

    'categories' => [
        'hosting'      => 'Hosting',
        'dominio'      => 'Dominio',
        'correo'       => 'Correo corporativo',
        'diseno'       => 'Diseño',
        'mantenimiento'=> 'Mantenimiento',
        'marketing'    => 'Marketing',
        'seo'          => 'SEO',
        'otro'         => 'Otro',
    ],

    'billing_cycles' => [
        'once'         => 'Pago único',
        'monthly'      => 'Mensual',
        'quarterly'    => 'Trimestral',
        'semiannual'   => 'Semestral',
        'annual'       => 'Anual',
        'custom_months'=> 'Cada N meses (personalizado)',
    ],

];
