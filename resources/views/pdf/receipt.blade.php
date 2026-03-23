<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Pago - {{ $client->business_name }}</title>
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

        .header-title {
            font-size: 28px;
            font-weight: bold;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* CONTENT WRAPPER */
        .content {
            padding: 40px;
        }

        /* INFO SECTION */
        .info-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .info-title {
            font-size: 14px;
            font-weight: bold;
            color: #777;
            text-transform: uppercase;
        }

        .info-data {
            font-size: 16px;
            color: #111;
        }

        .text-right {
            text-align: right;
        }

        /* RECEIPT TABLE */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .receipt-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #264ab3;
            border-bottom: 2px solid #264ab3;
            background-color: #f7f9fd;
            text-transform: uppercase;
        }

        .receipt-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #eaeaea;
            vertical-align: top;
        }

        .concept-title {
            font-weight: bold;
            font-size: 16px;
            color: #111;
        }

        .concept-desc {
            font-size: 13px;
            color: #555;
            margin-top: 5px;
        }

        .amount-col {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        /* TOTALS */
        .totals-container {
            margin-top: 30px;
            width: 100%;
            text-align: right;
        }

        .total-box {
            display: inline-block;
            background-color: #f7f9fd;
            border: 2px solid #264ab3;
            padding: 20px 30px;
            border-radius: 8px;
            min-width: 200px;
            text-align: center;
        }

        .total-label {
            font-size: 14px;
            font-weight: bold;
            color: #264ab3;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .total-value {
            font-size: 28px;
            font-weight: bold;
            color: #16a34a;
        }

        .footer-note {
            margin-top: 60px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eaeaea;
            padding-top: 20px;
        }
    </style>
</head>

<body>

    @php
        $logoPath = public_path('logo-white.svg');
        $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
        $logoSrc = 'data:image/svg+xml;base64,' . $logoData;
    @endphp

    <!-- Header Banner -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-container" style="width: 50%;">
                    @if($logoData)
                        <img src="{{ $logoSrc }}" alt="Logo">
                    @else
                        <h2 style="margin:0; font-size: 32px;">LUNAVALOS</h2>
                    @endif
                </td>
                <td class="header-title" style="width: 50%;">
                    RECIBO DE PAGO
                </td>
            </tr>
        </table>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Info Section -->
        <table class="info-table">
            <tr>
                <td style="width: 50%;">
                    <div class="info-title">Cliente</div>
                    <div class="info-data"><strong>{{ $client->business_name }}</strong></div>
                    @if($client->contact_name)
                    <div class="info-data">{{ $client->contact_name }}</div>
                    @endif
                </td>
                <td style="width: 50%;" class="text-right">
                    <div class="info-title">Fecha de Emisión</div>
                    <div class="info-data">
                        {{ \Carbon\Carbon::now()->format('d / m / Y') }}
                    </div>
                    <div class="info-title" style="margin-top: 10px;">Vencimiento / Renovación</div>
                    <div class="info-data">
                        {{ \Carbon\Carbon::parse($client->next_renewal_date)->format('d / m / Y') }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Receipt Table -->
        <table class="receipt-table">
            <tr>
                <th style="width: 70%;">Descripción del Servicio</th>
                <th style="width: 30%; text-align: right;">Importe</th>
            </tr>
            
            @if($billing_type === 'monthly')
            <tr>
                <td>
                    <div class="concept-title">Cuota de Servicio Mensual</div>
                    <div class="concept-desc">
                        Servicio contratado: <strong>{{ $service_name }}</strong><br>
                        Correspondiente al mes de: {{ \Carbon\Carbon::parse($client->next_renewal_date)->translatedFormat('F Y') }}
                    </div>
                </td>
                <td class="amount-col">
                    ${{ number_format($amount, 2) }}
                </td>
            </tr>
            @else
            <tr>
                <td>
                    <div class="concept-title">Renovación de Cuenta / Servicios Anuales</div>
                    <div class="concept-desc">
                        Paquete / Servicio contratado: <strong>{{ $service_name }}</strong><br>
                        Periodo de renovación a partir del: {{ \Carbon\Carbon::parse($client->next_renewal_date)->format('d M Y') }}
                    </div>
                </td>
                <td class="amount-col">
                    ${{ number_format($amount, 2) }}
                </td>
            </tr>
            @endif
        </table>

        <!-- Totals -->
        <div class="totals-container">
            <div class="total-box">
                <div class="total-label">Total a Pagar</div>
                <div class="total-value">${{ number_format($amount, 2) }}</div>
            </div>
        </div>

        <div class="footer-note">
            Gracias por su preferencia y confianza.<br>
            Cualquier duda administrativa o de soporte, por favor contáctenos.
        </div>
    </div>
</body>

</html>
