<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cotización #{{ $quote->id }}</title>
    <style>
        @page {
            margin: 110px 0px 40px 0px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        /* HEADER BANNER */
        .header {
            position: fixed;
            top: -110px;
            left: 0px;
            right: 0px;
            background-color: #193074;
            color: #ffffff;
            padding: 14px 32px;
            height: 60px;
            z-index: 1000;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-container img {
            height: 46px;
        }

        .year {
            font-size: 38px;
            font-weight: bold;
            text-align: right;
        }

        /* CONTENT WRAPPER */
        .content {
            padding: 24px 32px;
        }

        /* INFO SECTION */
        .info-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 5px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .info-title {
            font-size: 16px;
            font-weight: 700;
        }

        .info-client {
            font-size: 13px;
        }

        .text-right {
            text-align: right;
        }

        /* QUOTE TABLE */
        .quote-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .quote-table th {
            padding: 7px 8px;
            text-align: center;
            font-size: 12px;
            color: #264ab3;
            border: 1px solid #eaeaea;
            background-color: #f7f9fd;
        }

        .quote-table .header-main {
            background-color: #eff3f9;
        }

        .quote-table td {
            padding: 6px 8px;
            border: 1px solid #eaeaea;
            vertical-align: top;
        }

        .concept-title {
            font-weight: 700;
            font-size: 12px;
            color: #111;
            margin-bottom: 2px;
        }

        .concept-desc {
            font-style: italic;
            font-size: 11px;
            color: #777;
        }

        .concept-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
            margin-left: 5px;
            vertical-align: middle;
        }

        .badge-unique {
            background-color: #e9d5ff;
            color: #6b21a8;
        }

        .badge-monthly {
            background-color: #bbf7d0;
            color: #166534;
        }

        .badge-annual {
            background-color: #fef08a; /* yellow-200 */
            color: #854d0e; /* yellow-800 */
        }

        .text-center {
            text-align: center;
        }

        /* FOOTER TOTALS */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: -1px;
            /* collapse border with table above */
        }

        .totals-table td {
            padding: 6px 10px;
            border: 1px solid #eaeaea;
        }

        .totals-label {
            text-align: right;
            font-weight: bold;
            color: #264ab3;
            background-color: #f7f9fd;
            width: 80%;
        }

        .totals-value {
            text-align: right;
            font-weight: bold;
            color: #16a34a;
            width: 20%;
            font-size: 13px;
        }

        .notes-section {
            margin-top: 16px;
            color: #555;
            font-size: 11px;
            line-height: 1.35;
        }
        .notes-section h1, .notes-section h2, .notes-section h3 {
            color: #333;
            margin-bottom: 4px;
            margin-top: 8px;
        }
        .notes-section ul, .notes-section ol {
            margin-left: 18px;
            margin-bottom: 6px;
        }
        .notes-section li {
            margin-bottom: 2px;
        }
        .notes-section strong {
            color: #333;
        }
    </style>
</head>

<body>

    <!-- Prepare Logo as Base64 for DomPDF compatibility -->
    @php
        $logoPath = public_path('logo-white.svg');
        $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
        $logoSrc = 'data:image/svg+xml;base64,' . $logoData;

        // Moneda activa de esta cotización (fallback a la default configurada).
        $qcur = $quote->currency ?? config('currencies.default');

        // Buckets de totales.
        //
        // Tipo de cobro ANUAL: el precio base (unit_price) es el PAGO INICIAL
        // ÚNICO por el desarrollo del proyecto — el gasto fuerte de arranque,
        // financiable en el plan de meses. El Precio de Renovación
        // (unit_renewal_price) es la ANUALIDAD que cubre los servicios de cada
        // año (dominio, hosting, soporte) y se cobra a partir del año 2, por lo
        // que NO forma parte del total a pagar hoy.
        $uniqueTotal        = 0; // pagos únicos "clásicos"
        $monthlyTotal       = 0; // iguala / mensualidad recurrente
        $annualBaseTotal    = 0; // desarrollo inicial de los servicios anuales
        $annualAddonTotal   = 0; // addons anuales: el año 1 va incluido hoy
        $annualRenewalTotal = 0; // anualidad recurrente (año 2 en adelante)
        foreach ($quote->items as $item) {
            $lineTotal = $item->unit_price * $item->quantity;
            if ($item->billing_type == 'unique') {
                $uniqueTotal += $lineTotal;
            } elseif ($item->billing_type == 'annual') {
                $annualBaseTotal += $lineTotal;
            } else {
                $monthlyTotal += $lineTotal;
            }
            // Cualquier item puede traer renovación anual, sea anual o único.
            $annualRenewalTotal += ($item->unit_renewal_price ?? 0) * $item->quantity;
        }

        // Mapeo billing_cycle (addon) → bucket totales/badge.
        $addonBucket = function ($cycle) {
            return match ($cycle) {
                'monthly'                       => 'monthly',
                'annual', 'semiannual'          => 'annual',
                'unique', 'one_time', 'once'    => 'unique',
                default                         => 'monthly',
            };
        };

        foreach ($quote->addons ?? [] as $addon) {
            $line = $addon->unit_price * $addon->quantity;
            $bucket = $addonBucket($addon->billing_cycle);
            if ($bucket === 'unique') {
                $uniqueTotal += $line;
            } elseif ($bucket === 'annual') {
                // Año 1 incluido en el total de hoy y recurrente desde el año 2.
                $annualAddonTotal   += $line;
                $annualRenewalTotal += $line;
            } else {
                $monthlyTotal += $line;
            }
        }

        // Total anual cobrado HOY (arranque del proyecto + primer año de addons).
        $annualTotal = $annualBaseTotal + $annualAddonTotal;
    @endphp

    <!-- Header Banner -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-container" style="width: 50%;">
                    @if($logoData)
                        <img src="{{ $logoSrc }}" alt="LunAvalos Logo">
                    @else
                        <h2 style="margin:0; font-size: 36px;">LUNAVALOS</h2>
                    @endif
                </td>
                <td class="year" style="width: 50%;">
                    {{ date('Y') }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Info Section -->
        <table class="info-table">
            <tr>
                <td style="width: 60%;" class="info-title">
                    Cotización de Servicios
                </td>
                <td style="width: 40%;" class="text-right">
                    Fecha de Emisión: {{ $quote->issue_date->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td style="width: 60%;" class="info-client">
                    Cliente: <strong>{{ $quote->client_name }}</strong>
                </td>
                <td style="width: 40%;" class="text-right">
                    @if($quote->valid_until)
                        válido hasta: {{ $quote->valid_until->format('d/m/Y') }}
                    @endif
                </td>
        </table>

        <!-- Items Table -->
        <table class="quote-table">
            <tr>
                <th colspan="3" class="header-main">
                    @if($quote->is_multiple_choice)
                        OPCIONES DISPONIBLES (ELEGIR UNA)
                    @else
                        SERVICIOS
                    @endif
                </th>
            </tr>
            <tr>
                <th style="width: 70%; text-align: left;">CONCEPTO</th>
                <th style="width: 10%; text-align: center;">CANT</th>
                <th style="width: 20%; text-align: right;">SUBTOTAL</th>
            </tr>

            @foreach($quote->items as $item)
                <tr>
                    <td>
                        <div class="concept-title">
                            {{ $item->concept }}
                            @if($item->billing_type == 'annual')
                                <span class="concept-badge badge-unique">PAGO INICIAL ÚNICO</span>
                                @if(($item->unit_renewal_price ?? 0) > 0)
                                    <span class="concept-badge badge-annual">+ ANUALIDAD</span>
                                @endif
                            @elseif($item->billing_type == 'unique')
                                <span class="concept-badge badge-unique">PAGO ÚNICO</span>
                                @if(($item->unit_renewal_price ?? 0) > 0)
                                    <span class="concept-badge badge-annual">+ ANUALIDAD</span>
                                @endif
                            @else
                                <span class="concept-badge badge-monthly">MENSUAL</span>
                                @if(($item->unit_renewal_price ?? 0) > 0)
                                    <span class="concept-badge badge-annual">+ ANUALIDAD</span>
                                @endif
                            @endif
                        </div>
                        @if($item->description)
                            <div class="concept-desc">
                                {!! nl2br(e($item->description)) !!}
                            </div>
                        @endif
                        @if($item->service && $item->service->features->isNotEmpty())
                            <div class="concept-features" style="margin-top: 6px; padding-left: 6px;">
                                <ul style="margin: 0; padding: 0; list-style: none;">
                                    @foreach($item->service->features as $feature)
                                        <li style="font-size: 10px; color: #555; margin-bottom: 2px; line-height: 1.2;">
                                            <span style="color: #10b981; font-weight: bold; margin-right: 4px;">&bull;</span> {{ $feature->label }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td class="text-center" style="vertical-align: middle;">
                        {{ $item->quantity }}
                    </td>
                    <td class="text-right" style="vertical-align: middle;">
                        @money($item->unit_price * $item->quantity, $qcur)
                        @if($item->billing_type == 'monthly')
                            <div style="font-size: 9px; font-weight: normal; color: #555;">al mes</div>
                        @endif
                        @if(($item->unit_renewal_price ?? 0) > 0)
                            <div style="font-size: 10px; font-weight: normal; color: #b45309; margin-top: 4px;">
                                + @money($item->unit_renewal_price * $item->quantity, $qcur) / a&ntilde;o
                                <div style="font-size: 9px; color: #92400e;">desde el a&ntilde;o 2</div>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach

            @php
                $cycleLabels = config('service_addons.billing_cycles', []);
            @endphp
            @foreach($quote->addons ?? [] as $addon)
                @php
                    $bucket = $addonBucket($addon->billing_cycle);
                    $cycleLabel = $cycleLabels[$addon->billing_cycle] ?? ucfirst($addon->billing_cycle);
                @endphp
                <tr>
                    <td>
                        <div class="concept-title">
                            {{ $addon->serviceAddon->name ?? 'Servicio adicional' }}
                            @if($bucket === 'unique')
                                <span class="concept-badge badge-unique">PAGO ÚNICO</span>
                            @elseif($bucket === 'annual')
                                <span class="concept-badge badge-annual">{{ strtoupper($cycleLabel) }}</span>
                            @else
                                <span class="concept-badge badge-monthly">{{ strtoupper($cycleLabel) }}</span>
                            @endif
                            @if($addon->is_required)
                                <span class="concept-badge" style="background-color:#fde68a;color:#92400e;">OBLIGATORIO</span>
                            @endif
                        </div>
                        @if($addon->serviceAddon && $addon->serviceAddon->description)
                            <div class="concept-desc">{!! nl2br(e($addon->serviceAddon->description)) !!}</div>
                        @endif
                    </td>
                    <td class="text-center" style="vertical-align: middle;">
                        {{ $addon->quantity }}
                    </td>
                    <td class="text-right" style="vertical-align: middle;">
                        @money($addon->unit_price * $addon->quantity, $qcur)
                    </td>
                </tr>
            @endforeach
        </table>

        <!-- Totals Table -->
        @if(!$quote->is_multiple_choice)
            @php
                $planMonths       = (int) ($quote->package_payment_plan_months ?? 0);
                // El plan de pagos sólo aplica para financiar paquetes únicos/anuales en cuotas.
                // Si la cotización ya tiene servicios mensuales recurrentes, NO se divide: se muestra IGUALA MENSUAL.
                $usePlanSplit     = $planMonths > 1 && $monthlyTotal <= 0;
                $monthlyPlanNet   = $usePlanSplit ? round(((float) $quote->subtotal) / $planMonths, 2) : 0;
                $monthlyPlanGross = $usePlanSplit ? round(((float) $quote->total)    / $planMonths, 2) : 0;
                $hasIvaBreakdown  = $quote->applies_iva && (float) $quote->iva_amount > 0;
                // Factor para equivalentes "con impuestos" en cifras agregadas.
                $taxFactor        = ((float) $quote->subtotal) > 0
                    ? ((float) $quote->total) / ((float) $quote->subtotal)
                    : 1;
            @endphp

            {{-- 1) Totales SIN impuestos (flujo natural del cliente). --}}
            <table class="totals-table">
                @if($monthlyPlanNet > 0)
                    <tr>
                        <td class="totals-label">INVERSI&Oacute;N MENSUAL
                            <span style="font-weight:normal; font-size:10px;">(plan a {{ $planMonths }} meses)</span>
                        </td>
                        <td class="totals-value">@money($monthlyPlanNet, $qcur)</td>
                    </tr>
                @elseif($monthlyTotal > 0)
                    <tr>
                        <td class="totals-label">IGUALA MENSUAL
                            <span style="font-weight:normal; font-size:10px;">(servicios recurrentes)</span>
                        </td>
                        <td class="totals-value">@money($monthlyTotal, $qcur)</td>
                    </tr>
                @endif

                @if($annualBaseTotal > 0)
                    <tr>
                        <td class="totals-label">INVERSI&Oacute;N INICIAL
                            <span style="font-weight:normal; font-size:10px;">(desarrollo del proyecto &middot; pago &uacute;nico)</span>
                        </td>
                        <td class="totals-value">@money($annualBaseTotal, $qcur)</td>
                    </tr>
                @endif

                @if($annualAddonTotal > 0)
                    <tr>
                        <td class="totals-label">SERVICIOS ANUALES
                            <span style="font-weight:normal; font-size:10px;">(primer a&ntilde;o, incluido en este pago)</span>
                        </td>
                        <td class="totals-value">@money($annualAddonTotal, $qcur)</td>
                    </tr>
                @endif

                @if($uniqueTotal > 0)
                    <tr>
                        <td class="totals-label">TOTAL PAGO &Uacute;NICO <span style="font-weight:normal; font-size:10px;">(un solo pago)</span></td>
                        <td class="totals-value">@money($uniqueTotal, $qcur)</td>
                    </tr>
                @endif

                @if($annualRenewalTotal > 0)
                    <tr>
                        <td class="totals-label" style="color: #b45309; background-color: #fffbeb;">
                            RENOVACI&Oacute;N ANUAL
                            <span style="font-weight:normal; font-size:10px;">(cada a&ntilde;o, a partir del a&ntilde;o 2 &middot; no incluida en el total de hoy)</span>
                        </td>
                        <td class="totals-value" style="color: #b45309; background-color: #fffbeb;">
                            @money($annualRenewalTotal, $qcur)
                        </td>
                    </tr>
                @endif

                @if($uniqueTotal <= 0 && $monthlyTotal <= 0 && $annualTotal <= 0)
                    <tr>
                        <td class="totals-label">TOTAL</td>
                        <td class="totals-value">$0.00</td>
                    </tr>
                @endif
            </table>
        @endif

        @php
            $hasTaxSnapshot = ($quote->applies_iva || $quote->applies_isr_retention || $quote->applies_iva_retention)
                || ((float)($quote->subtotal ?? 0) > 0);
        @endphp

        {{-- 2) Desglose fiscal: SUBTOTAL → IVA/retenciones → TOTAL A PAGAR. --}}
        @if(!$quote->is_multiple_choice && !$quote->legacy && $hasTaxSnapshot)
            <table class="totals-table" style="margin-top: 14px;">
                <tr>
                    <td class="totals-label" style="background-color:#eff3f9; color:#264ab3;">SUBTOTAL</td>
                    <td class="totals-value" style="color:#111;">@money($quote->subtotal, $qcur)</td>
                </tr>
                @if((float) $quote->discount_amount > 0)
                    <tr>
                        <td class="totals-label">DESCUENTO</td>
                        <td class="totals-value" style="color:#b45309;">- @money($quote->discount_amount, $qcur)</td>
                    </tr>
                @endif
                @if($quote->applies_iva && (float) $quote->iva_amount > 0)
                    <tr>
                        <td class="totals-label">IVA TRASLADADO ({{ rtrim(rtrim(number_format($quote->iva_rate, 4), '0'), '.') }}%)</td>
                        <td class="totals-value" style="color:#264ab3;">+ @money($quote->iva_amount, $qcur)</td>
                    </tr>
                @endif
                @if($quote->applies_isr_retention && (float) $quote->isr_retention_amount > 0)
                    <tr>
                        <td class="totals-label">RET. ISR ({{ rtrim(rtrim(number_format($quote->isr_retention_rate, 4), '0'), '.') }}%)</td>
                        <td class="totals-value" style="color:#b91c1c;">- @money($quote->isr_retention_amount, $qcur)</td>
                    </tr>
                @endif
                @if($quote->applies_iva_retention && (float) $quote->iva_retention_amount > 0)
                    <tr>
                        <td class="totals-label">RET. IVA ({{ rtrim(rtrim(number_format($quote->iva_retention_rate, 4), '0'), '.') }}%)</td>
                        <td class="totals-value" style="color:#b91c1c;">- @money($quote->iva_retention_amount, $qcur)</td>
                    </tr>
                @endif
                <tr>
                    <td class="totals-label" style="background-color:#ecfdf5; color:#065f46; font-size:14px;">TOTAL A PAGAR</td>
                    <td class="totals-value" style="color:#16a34a; font-size:15px;">@money($quote->total, $qcur)</td>
                </tr>
                @if($quote->tax_regime)
                    @php $regimes = config('sat.tax_regimes'); $regLabel = $regimes[$quote->tax_regime]['label'] ?? null; @endphp
                    <tr>
                        <td colspan="2" style="font-size:10px; color:#555; background-color:#fafafa; text-align:right;">
                            Régimen fiscal: <strong>{{ $quote->tax_regime }}</strong>@if($regLabel) · {{ $regLabel }}@endif
                        </td>
                    </tr>
                @endif
            </table>

            {{-- 3) Equivalentes CON impuestos (sólo si aplica IVA). --}}
            @if(!$quote->is_multiple_choice && $hasIvaBreakdown && ($monthlyPlanGross > 0 || $monthlyTotal > 0 || $annualTotal > 0 || $uniqueTotal > 0))
                <table class="totals-table" style="margin-top: 14px;">
                    <tr>
                        <td colspan="2" style="background-color:#fffbeb; color:#92400e; font-size:11px; padding:6px 10px; text-align:right;">
                            <strong>Equivalente con impuestos</strong>
                        </td>
                    </tr>
                    @if($monthlyPlanGross > 0)
                        <tr>
                            <td class="totals-label" style="background-color:#fffbeb; color:#92400e;">INVERSI&Oacute;N MENSUAL
                                <span style="font-weight:normal; font-size:10px;">(con impuestos)</span>
                            </td>
                            <td class="totals-value" style="background-color:#fffbeb; color:#92400e;">@money($monthlyPlanGross, $qcur)</td>
                        </tr>
                    @elseif($monthlyTotal > 0)
                        <tr>
                            <td class="totals-label" style="background-color:#fffbeb; color:#92400e;">IGUALA MENSUAL
                                <span style="font-weight:normal; font-size:10px;">(con impuestos)</span>
                            </td>
                            <td class="totals-value" style="background-color:#fffbeb; color:#92400e;">@money(round($monthlyTotal * $taxFactor, 2), $qcur)</td>
                        </tr>
                    @endif
                    @if($annualTotal > 0 && $monthlyPlanGross <= 0)
                        <tr>
                            <td class="totals-label" style="background-color:#fffbeb; color:#92400e;">INVERSI&Oacute;N INICIAL
                                <span style="font-weight:normal; font-size:10px;">(con impuestos)</span>
                            </td>
                            <td class="totals-value" style="background-color:#fffbeb; color:#92400e;">@money(round($annualTotal * $taxFactor, 2), $qcur)</td>
                        </tr>
                    @endif
                    @if($annualRenewalTotal > 0)
                        <tr>
                            <td class="totals-label" style="background-color:#fffbeb; color:#92400e;">RENOVACI&Oacute;N ANUAL
                                <span style="font-weight:normal; font-size:10px;">(con impuestos &middot; desde el a&ntilde;o 2)</span>
                            </td>
                            <td class="totals-value" style="background-color:#fffbeb; color:#92400e;">@money(round($annualRenewalTotal * $taxFactor, 2), $qcur)</td>
                        </tr>
                    @endif
                    {{-- @if($uniqueTotal > 0)
                        <tr>
                            <td class="totals-label" style="background-color:#fffbeb; color:#92400e;">TOTAL PAGO &Uacute;NICO
                                <span style="font-weight:normal; font-size:10px;">(con impuestos)</span>
                            </td>
                            <td class="totals-value" style="background-color:#fffbeb; color:#92400e;">@money(round($uniqueTotal * $taxFactor, 2), $qcur)</td>
                        </tr>
                    @endif --}}
                </table>
            @endif
        @endif

        {{-- Cómo se compone el pago: inversión inicial vs. anualidad. --}}
        @if(!$quote->is_multiple_choice && ($annualBaseTotal > 0 || $annualRenewalTotal > 0))
            @php
                $planMonths = (int) ($quote->package_payment_plan_months ?? 0);
                $upfrontTotal = $annualBaseTotal + $annualAddonTotal + $uniqueTotal;
            @endphp
            <div class="notes-section" style="margin-bottom: 15px; border: 1px solid #fde68a; background-color: #fffbeb; padding: 8px 10px; border-radius: 4px;">
                <strong style="color:#92400e;">C&oacute;mo funciona tu inversi&oacute;n</strong>
                <ol style="margin-top: 4px; color:#4b5563;">
                    @if($upfrontTotal > 0)
                        <li>
                            <strong>Pago inicial de @money($upfrontTotal, $qcur) (una sola vez).</strong>
                            Cubre el desarrollo y la puesta en marcha del proyecto.
                            @if($planMonths > 1)
                                Se puede diferir en <strong>{{ $planMonths }} mensualidades</strong> de
                                @money(round($upfrontTotal / $planMonths, 2), $qcur).
                            @endif
                            No se vuelve a cobrar.
                        </li>
                    @endif
                    @if($monthlyTotal > 0)
                        <li>
                            <strong>Iguala mensual de @money($monthlyTotal, $qcur) al mes.</strong>
                            Es el cobro recurrente de los servicios contratados mes con mes,
                            mientras el servicio siga activo.
                        </li>
                    @endif
                    @if($annualRenewalTotal > 0)
                        <li>
                            <strong>Anualidad de @money($annualRenewalTotal, $qcur) por a&ntilde;o, a partir del a&ntilde;o 2.</strong>
                            Se cobra una vez al a&ntilde;o, aparte de {{ $monthlyTotal > 0 ? 'la iguala mensual' : 'lo anterior' }},
                            y cubre los servicios que mantienen el proyecto en l&iacute;nea (dominio, hosting,
                            soporte y mantenimiento). El primer a&ntilde;o ya est&aacute; incluido.
                        </li>
                    @endif
                </ol>
                <div style="font-size: 10px; color: #92400e; margin-top: 4px;">
                    La renovaci&oacute;n anual no forma parte del total a pagar de esta cotizaci&oacute;n.
                </div>
            </div>
        @endif

        <!-- Notes Section underneath -->
        @if(($uniqueTotal > 0 || $monthlyTotal > 0 || $annualBaseTotal > 0) && $quote->include_payment_terms)
            @php
                $planMonths = (int) ($quote->package_payment_plan_months ?? 0);
                $upfrontOnce = $uniqueTotal + $annualBaseTotal + $annualAddonTotal;
            @endphp
            @if($upfrontOnce <= 0 && $monthlyTotal > 0)
                {{-- Iguala pura: el plan de meses es el compromiso, no un saldo diferido. --}}
                <div class="notes-section" style="margin-bottom: 15px; color: #16a34a; font-size: 14px;">
                    <strong>Condiciones de Pago (Iguala mensual):</strong>
                    @money($monthlyTotal, $qcur) al mes, pagaderos por adelantado{{ $planMonths > 1 ? " durante {$planMonths} meses de compromiso" : '' }}.
                </div>
            @elseif($planMonths > 1)
                <div class="notes-section" style="margin-bottom: 15px; color: #16a34a; font-size: 14px;">
                    <strong>Condiciones de Pago (Plan a {{ $planMonths }} mensualidades):</strong>
                    Pago inicial al contratar + {{ $planMonths - 1 }} {{ $planMonths - 1 === 1 ? 'mensualidad' : 'mensualidades' }} del saldo restante.
                </div>
            @elseif($upfrontOnce > 0)
                <div class="notes-section" style="margin-bottom: 15px; color: #16a34a; font-size: 14px;">
                    <strong>Condiciones de Proyecto ("Pago Único"):</strong> 50% de anticipo al inicio
                    (@money($upfrontOnce / 2, $qcur)) y 50% restante al entregar
                    (@money($upfrontOnce / 2, $qcur)).
                </div>
            @endif
        @endif

        @if($quote->duration)
            <div class="notes-section" style="margin-bottom: 10px; color: #333; font-style: normal; font-size: 14px;">
                Duración/Compromiso: <strong>{{ $quote->duration }}</strong>
            </div>
        @endif

        @if(trim($quote->notes))
            <div class="notes-section" style="margin-top: {{ $quote->duration ? '10px' : '30px' }};">
                {!! $quote->notes !!}
            </div>
        @endif

    </div>
</body>

</html>