<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $contract->contract_number ?? ('#' . $contract->id) }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            color: #1a202c;
            line-height: 1.6;
        }
        .page { padding: 40px 52px 36px; }

        .header {
            background-color: #264ab3;
            color: #fff;
            padding: 18px 52px;
            margin: -40px -52px 28px;
        }
        .header-table { width: 100%; }
        .header-title { font-size: 14px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .header-sub { font-size: 8.5px; opacity: 0.7; margin-top: 3px; }
        .header-meta { text-align: right; font-size: 9px; opacity: 0.85; }

        .parties {
            margin-bottom: 16px;
            background: #f7f9ff;
            border-left: 3px solid #264ab3;
            padding: 10px 14px;
        }
        .parties p { margin-bottom: 4px; }

        h3.clause {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #264ab3;
            margin: 16px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #dde3f5;
        }
        p { margin-bottom: 6px; text-align: justify; }
        ul { margin: 4px 0 8px 18px; padding: 0; }
        li { margin-bottom: 3px; }

        .amount-box {
            background: #eff4ff;
            border: 1px solid #c7d8f8;
            border-radius: 4px;
            padding: 10px 14px;
            margin: 8px 0;
        }
        .amount-box .amount-main { font-size: 16px; font-weight: bold; color: #264ab3; }
        .amount-box .amount-label { font-size: 9px; color: #555; margin-top: 2px; }

        .signatures { margin-top: 32px; }
        .sig-table { width: 100%; }
        .sig-box { width: 46%; vertical-align: top; padding: 12px 14px; background: #f9fafb; border: 1px solid #e2e8f0; }
        .sig-box .sig-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #264ab3; margin-bottom: 8px; }
        .sig-box p { margin-bottom: 3px; text-align: left; }
        .sig-line { border-top: 1px solid #aaa; margin-top: 30px; padding-top: 5px; font-size: 8.5px; color: #666; }

        .footer {
            font-size: 8px;
            color: #aaa;
            text-align: center;
            margin-top: 28px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        @if($contract->status !== 'signed')
        .draft-mark {
            position: fixed; top: 35%; left: 10%;
            font-size: 80px; font-weight: bold; color: rgba(200,50,50,0.10);
            transform: rotate(-35deg); letter-spacing: 10px;
            pointer-events: none; z-index: 999;
        }
        @endif
    </style>
</head>
<body>
<div class="page">

    @if($contract->status !== 'signed')
    <div class="draft-mark">BORRADOR</div>
    @endif

    @php
        $cur        = $contract->currency ?? config('currencies.default', 'MXN');
        $provider   = $settings['company_legal_name'] ?? $settings['company_commercial_name'] ?? 'Luna Avalos';
        $site       = $settings['company_website'] ?? 'lunavalos.com';
        $isRenewal  = !$contract->quote_id || !floatval($contract->anticipo_amount ?? 0);

        // Services: prefer quote items, fall back to client_services
        $services = [];
        if ($contract->quote && $contract->quote->items && count($contract->quote->items)) {
            foreach ($contract->quote->items as $item) {
                $services[] = $item->concept . ($item->description ? ' — ' . $item->description : '');
            }
        } elseif ($contract->client && $contract->client->services) {
            foreach ($contract->client->services as $s) {
                $services[] = $s->service_name;
            }
        }

        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '—';
    @endphp

    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">Contrato de Prestación de Servicios</div>
                    <div class="header-sub">{{ $provider }} · {{ $site }}</div>
                </td>
                <td class="header-meta">
                    <div>No. <strong>{{ $contract->contract_number ?? ('#' . $contract->id) }}</strong></div>
                    <div>{{ $fmtDate($contract->start_date ?? $contract->created_at) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Partes -->
    <div class="parties">
        <p>Entre: <strong>{{ $provider }}</strong> — {{ $site }} <em>("EL PRESTADOR")</em></p>
        <p>y: <strong>{{ $contract->legal_name }}</strong>
            @if($contract->tax_id) · RFC: {{ $contract->tax_id }} @endif
            <em>("EL CLIENTE")</em>
        </p>
        <p>Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA: Objeto -->
    <h3 class="clause">Primera. Objeto del Contrato</h3>
    <p>EL PRESTADOR se obliga a mantener activos y en correcto funcionamiento los servicios digitales
    contratados por EL CLIENTE, garantizando que operen de manera continua durante la vigencia del
    presente contrato, con el respaldo técnico necesario para su funcionamiento.</p>
    <p><strong>Servicios contratados:</strong></p>
    @if(count($services))
    <ul>
        @foreach($services as $svc)
            <li>{{ $svc }}</li>
        @endforeach
    </ul>
    @else
    <p><em>— Servicios descritos en cotización adjunta —</em></p>
    @endif
    @if($contract->quote_id)
    <p>Con referencia a la cotización No. <strong>{{ $contract->quote?->id }}</strong>,
    que forma parte integral del presente contrato.</p>
    @endif

    <!-- SEGUNDA: Vigencia -->
    <h3 class="clause">Segunda. Vigencia del Servicio</h3>
    <p>El presente contrato tiene una vigencia de <strong>doce (12) meses</strong>
    @if($contract->start_date)
        , a partir del <strong>{{ $fmtDate($contract->start_date) }}</strong>
        @if($contract->end_date)
            con vencimiento el <strong>{{ $fmtDate($contract->end_date) }}</strong>
        @endif
    @endif.</p>
    <p>Al término de la vigencia, EL CLIENTE podrá optar por renovar el servicio por un periodo adicional
    mediante el pago de la tarifa correspondiente. La no renovación implica la suspensión de los servicios
    al concluir el periodo pagado.</p>

    <!-- TERCERA: Monto y Pago -->
    <h3 class="clause">Tercera. Monto y Forma de Pago</h3>
    @if($isRenewal)
        <p>El monto correspondiente a la renovación anual de los servicios es:</p>
        <div class="amount-box">
            <div class="amount-main">@money($contract->total_amount, $cur)</div>
            <div class="amount-label">Pago único de renovación anual</div>
        </div>
        <p>El pago deberá realizarse previo al vencimiento del servicio para garantizar la continuidad sin
        interrupciones. EL PRESTADOR notificará a EL CLIENTE con anticipación la fecha de vencimiento y
        el monto de renovación vigente.</p>
    @else
        <p>El monto total de los servicios contratados es:</p>
        <ul>
            @if($contract->subtotal)
            <li>Subtotal: <strong>@money($contract->subtotal, $cur)</strong></li>
            @endif
            @if($contract->iva_amount)
            <li>IVA (16%): <strong>@money($contract->iva_amount, $cur)</strong></li>
            @endif
            <li>Total: <strong>@money($contract->total_amount, $cur)</strong></li>
        </ul>
        <p><strong>Plan de pago:</strong>
        Anticipo al inicio: <strong>@money($contract->anticipo_amount, $cur)</strong>.
        @if(($contract->payment_plan_months ?? 1) > 1)
        Saldo restante en <strong>{{ $contract->payment_plan_months - 1 }} mensualidades</strong>
        de <strong>@money($contract->monthly_amount, $cur)</strong>.
        @endif
        Los trabajos iniciarán una vez confirmado el anticipo.</p>
    @endif

    <!-- CUARTA: Compromisos del Prestador -->
    <h3 class="clause">Cuarta. Compromisos del Prestador</h3>
    <p>EL PRESTADOR se compromete a:</p>
    <ul>
        <li>Mantener los servicios contratados activos y operando correctamente durante la vigencia del contrato.</li>
        <li>Atender reportes de fallas o solicitudes de soporte en un plazo de <strong>3 a 5 días hábiles</strong> a partir de la notificación de EL CLIENTE.</li>
        <li>Notificar con anticipación cualquier mantenimiento programado, cambio relevante o próximo vencimiento del servicio.</li>
        <li>Custodiar de manera confidencial los accesos, contraseñas y activos digitales relacionados con el servicio.</li>
    </ul>

    <!-- QUINTA: Compromisos del Cliente -->
    <h3 class="clause">Quinta. Compromisos del Cliente</h3>
    <p>EL CLIENTE se compromete a:</p>
    <ul>
        <li>Realizar los pagos en tiempo y forma según lo acordado en este contrato.</li>
        <li>Proporcionar oportunamente la información, materiales y accesos necesarios para la correcta prestación del servicio.</li>
        <li>Notificar a EL PRESTADOR cualquier cambio en sus datos de contacto o requerimientos del servicio.</li>
        <li>No compartir accesos o credenciales del servicio con terceros sin autorización de EL PRESTADOR.</li>
    </ul>

    <!-- SEXTA: Cancelación -->
    <h3 class="clause">Sexta. Cancelación</h3>
    <p>EL CLIENTE podrá cancelar el servicio en cualquier momento notificando por escrito a EL PRESTADOR.
    En caso de cancelación:</p>
    <ul>
        <li>Los servicios permanecerán activos hasta la fecha de vencimiento del periodo ya pagado, sin reembolso proporcional.</li>
        <li>EL PRESTADOR realizará la entrega de los activos digitales del cliente dentro de los 5 días hábiles posteriores a la solicitud.</li>
        <li>En caso de adeudo pendiente, la entrega de activos se efectuará una vez liquidado el saldo.</li>
    </ul>

    <!-- SÉPTIMA: Propiedad y Confidencialidad -->
    <h3 class="clause">Séptima. Propiedad y Confidencialidad</h3>
    <p>Los derechos sobre los activos digitales desarrollados exclusivamente para EL CLIENTE serán
    transferidos a su favor una vez liquidado el monto total del contrato.</p>
    <p>Ambas partes se obligan a mantener confidencialidad sobre la información, accesos y datos
    intercambiados durante la relación comercial, tanto durante la vigencia como tras la terminación
    del contrato.</p>

    <!-- OCTAVA: Aceptación -->
    <h3 class="clause">Octava. Aceptación</h3>
    <p>Las partes declaran haber leído y entendido el presente contrato en su totalidad, aceptando sus términos
    de manera libre y voluntaria. La firma electrónica o aceptación digital tiene validez legal equivalente a
    firma autógrafa, de conformidad con el <strong>Código de Comercio</strong> y la
    <strong>Ley de Firma Electrónica Avanzada</strong> vigentes en México.</p>

    <!-- Firmas -->
    <div class="signatures">
        <table class="sig-table">
            <tr>
                <td class="sig-box">
                    <div class="sig-title">El Prestador</div>
                    <p>Nombre: <strong>{{ $provider }}</strong></p>
                    <p>Sitio web: {{ $site }}</p>
                    @if($settings['company_email'] ?? false)
                    <p>Correo: {{ $settings['company_email'] }}</p>
                    @endif
                    <div class="sig-line">Firma autógrafa</div>
                </td>
                <td width="8%"></td>
                <td class="sig-box">
                    <div class="sig-title">El Cliente</div>
                    <p>Nombre: <strong>{{ $contract->legal_name }}</strong></p>
                    @if($contract->legal_representative)
                    <p>Representante Legal: {{ $contract->legal_representative }}</p>
                    @endif
                    @if($contract->tax_id)
                    <p>RFC: {{ $contract->tax_id }}</p>
                    @endif
                    @if($contract->status === 'signed' && $contract->signed_at)
                    <div class="sig-line">
                        ✓ Firmado electrónicamente el {{ \Carbon\Carbon::parse($contract->signed_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }}<br>
                        IP de firma: {{ $contract->signature_ip }}
                    </div>
                    @else
                    <div class="sig-line">Pendiente de firma electrónica</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ $provider }}
        @if($settings['company_email'] ?? false) · {{ $settings['company_email'] }} @endif
        · Generado el {{ now()->format('d/m/Y H:i') }}
        @if($contract->status !== 'signed') · DOCUMENTO NO FIRMADO @endif
    </div>

</div>
</body>
</html>
