<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cotización #{{ $quote->id }}</title>
    <style>
        @page {
            margin: 140px 0px 50px 0px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 14px;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        /* HEADER BANNER */
        .header {
            position: fixed;
            top: -140px;
            left: 0px;
            right: 0px;
            background-color: #193074;
            color: #ffffff;
            padding: 20px 40px;
            height: 70px;
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
            height: 55px;
        }

        .year {
            font-size: 48px;
            font-weight: bold;
            text-align: right;
        }

        /* CONTENT WRAPPER */
        .content {
            padding: 40px;
        }

        /* INFO SECTION */
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .info-title {
            font-size: 20px;
            font-weight: 700;
        }

        .info-client {
            font-size: 16px;
        }

        .text-right {
            text-align: right;
        }

        /* QUOTE TABLE */
        .quote-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .quote-table th {
            padding: 12px;
            text-align: center;
            font-size: 14px;
            color: #264ab3;
            border: 1px solid #eaeaea;
            background-color: #f7f9fd;
        }

        .quote-table .header-main {
            background-color: #eff3f9;
        }

        .quote-table td {
            padding: 12px;
            border: 1px solid #eaeaea;
            vertical-align: top;
        }

        .concept-title {
            font-weight: 700;
            font-size: 14px;
            color: #111;
            margin-bottom: 4px;
        }

        .concept-desc {
            font-style: italic;
            font-size: 12px;
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
            padding: 12px;
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
            font-size: 16px;
        }

        .notes-section {
            margin-top: 30px;
            color: #555;
            font-size: 12px;
            line-height: 1.5;
        }
        .notes-section h1, .notes-section h2, .notes-section h3 {
            color: #333;
            margin-bottom: 5px;
            margin-top: 10px;
        }
        .notes-section ul, .notes-section ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .notes-section li {
            margin-bottom: 3px;
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

        // Calculations
        $uniqueTotal = 0;
        $monthlyTotal = 0;
        $annualTotal = 0;
        foreach ($quote->items as $item) {
            $lineTotal = $item->unit_price * $item->quantity;
            if ($item->billing_type == 'unique') {
                $uniqueTotal += $lineTotal;
            } elseif ($item->billing_type == 'annual') {
                $annualTotal += $lineTotal;
            } else {
                $monthlyTotal += $lineTotal;
            }
        }
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
                            @if($item->billing_type == 'unique')
                                <span class="concept-badge badge-unique">PAGO ÚNICO</span>
                            @elseif($item->billing_type == 'annual')
                                <span class="concept-badge badge-annual">ANUAL</span>
                            @else
                                <span class="concept-badge badge-monthly">MENSUAL</span>
                            @endif
                        </div>
                        @if($item->description)
                            <div class="concept-desc">
                                {!! nl2br(e($item->description)) !!}
                            </div>
                        @endif
                    </td>
                    <td class="text-center" style="vertical-align: middle;">
                        {{ $item->quantity }}
                    </td>
                    <td class="text-right" style="vertical-align: middle;">
                        ${{ number_format($item->unit_price * $item->quantity, 2) }}
                    </td>
                </tr>
            @endforeach
        </table>

        <!-- Totals Table -->
        @if(!$quote->is_multiple_choice)
            <table class="totals-table">
                @if($monthlyTotal > 0)
                    <tr>
                        <td class="totals-label">INVERSIÓN MENSUAL</td>
                        <td class="totals-value">${{ number_format($monthlyTotal, 2) }}</td>
                    </tr>
                @endif
    
                @if($annualTotal > 0)
                    <tr>
                        <td class="totals-label">INVERSIÓN ANUAL</td>
                        <td class="totals-value">${{ number_format($annualTotal, 2) }}</td>
                    </tr>
                @endif
    
                @if($uniqueTotal > 0)
                    <tr>
                        <td class="totals-label">TOTAL PAGO ÚNICO</td>
                        <td class="totals-value">${{ number_format($uniqueTotal, 2) }}</td>
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

        <!-- Notes Section underneath -->
        @if($uniqueTotal > 0 && $quote->include_payment_terms)
            <div class="notes-section" style="margin-bottom: 15px; color: #16a34a; font-size: 14px;">
                <strong>Condiciones de Proyecto ("Pago Único"):</strong> 50% de anticipo al inicio
                (${{ number_format($uniqueTotal / 2, 2) }}) y 50% restante al entregar
                (${{ number_format($uniqueTotal / 2, 2) }}).
            </div>
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