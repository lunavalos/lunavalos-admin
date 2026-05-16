<?php

/**
 * Catálogos SAT mínimos para CFDI 4.0.
 * No pretende ser exhaustivo: incluye los regímenes y usos más usados
 * por nuestros clientes (personas físicas, morales, RESICO, etc.).
 */

return [

    /*
     * Regímenes fiscales (Catálogo c_RegimenFiscal del SAT).
     * Marcamos quién aplica (PF = persona física, PM = persona moral, AMBOS).
     */
    'tax_regimes' => [
        '601' => ['label' => 'General de Ley Personas Morales',                          'applies_to' => 'PM'],
        '603' => ['label' => 'Personas Morales con Fines no Lucrativos',                 'applies_to' => 'PM'],
        '605' => ['label' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',      'applies_to' => 'PF'],
        '606' => ['label' => 'Arrendamiento',                                            'applies_to' => 'PF'],
        '607' => ['label' => 'Régimen de Enajenación o Adquisición de Bienes',           'applies_to' => 'PF'],
        '608' => ['label' => 'Demás ingresos',                                           'applies_to' => 'PF'],
        '610' => ['label' => 'Residentes en el Extranjero sin Establecimiento Permanente en México', 'applies_to' => 'AMBOS'],
        '611' => ['label' => 'Ingresos por Dividendos (socios y accionistas)',           'applies_to' => 'PF'],
        '612' => ['label' => 'Personas Físicas con Actividades Empresariales y Profesionales', 'applies_to' => 'PF'],
        '614' => ['label' => 'Ingresos por intereses',                                   'applies_to' => 'PF'],
        '615' => ['label' => 'Régimen de los ingresos por obtención de premios',         'applies_to' => 'PF'],
        '616' => ['label' => 'Sin obligaciones fiscales',                                'applies_to' => 'PF'],
        '620' => ['label' => 'Sociedades Cooperativas de Producción que difieren ingresos', 'applies_to' => 'PM'],
        '621' => ['label' => 'Incorporación Fiscal',                                     'applies_to' => 'PF'],
        '622' => ['label' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'applies_to' => 'PM'],
        '623' => ['label' => 'Opcional para Grupos de Sociedades',                       'applies_to' => 'PM'],
        '624' => ['label' => 'Coordinados',                                              'applies_to' => 'PM'],
        '625' => ['label' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'applies_to' => 'PF'],
        '626' => ['label' => 'Régimen Simplificado de Confianza (RESICO)',               'applies_to' => 'AMBOS'],
    ],

    /*
     * Usos de CFDI (catálogo c_UsoCFDI). Los más comunes para servicios B2B.
     */
    'cfdi_uses' => [
        'G01' => 'Adquisición de mercancías',
        'G02' => 'Devoluciones, descuentos o bonificaciones',
        'G03' => 'Gastos en general',
        'I01' => 'Construcciones',
        'I02' => 'Mobiliario y equipo de oficina por inversiones',
        'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo y accesorios',
        'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios',
        'D10' => 'Pagos por servicios educativos (colegiaturas)',
        'P01' => 'Por definir',
        'S01' => 'Sin efectos fiscales',
        'CP01' => 'Pagos',
    ],

    /*
     * Tasas y retenciones por defecto sugeridas según régimen y tipo de persona.
     * Se aplican al precargar el formulario; siempre son editables.
     *
     * Valores frecuentes para servicios profesionales en México:
     *  - IVA 16% (8% zona fronteriza).
     *  - Persona Moral pagando a Persona Física (612): retiene 10% ISR y 10.667% IVA (2/3 de 16).
     *  - RESICO PF (626): retención ISR 1.25% al facturar a PM.
     *  - Entre PM no hay retenciones por defecto.
     */
    'defaults' => [
        'iva_rate'            => 16.00,
        'iva_rate_frontera'   => 8.00,
        'isr_ret_honorarios'  => 10.00,
        'iva_ret_2_3'         => 10.6667,
        'isr_ret_resico_pf'   => 1.25,
    ],
];
