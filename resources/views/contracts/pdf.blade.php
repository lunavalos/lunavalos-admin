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
            line-height: 1.55;
        }
        .page { padding: 40px 52px 36px; }

        /* Header */
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

        /* Contract number block */
        .contract-meta {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #264ab3;
        }
        .contract-meta .number { font-size: 13px; font-weight: bold; }
        .contract-meta .date { font-size: 10px; color: #555; margin-top: 3px; }

        /* Parties */
        .parties { margin-bottom: 16px; background: #f7f9ff; border-left: 3px solid #264ab3; padding: 10px 14px; }
        .parties p { margin-bottom: 4px; }

        /* Clauses */
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
        li { margin-bottom: 2px; }

        /* Signatures */
        .signatures { margin-top: 32px; }
        .sig-table { width: 100%; }
        .sig-box { width: 46%; vertical-align: top; padding: 12px 14px; background: #f9fafb; border: 1px solid #e2e8f0; }
        .sig-box .sig-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #264ab3; margin-bottom: 8px; }
        .sig-box p { margin-bottom: 3px; text-align: left; }
        .sig-line { border-top: 1px solid #aaa; margin-top: 30px; padding-top: 5px; font-size: 8.5px; color: #666; }
        .sig-electronic { font-size: 8px; color: #666; margin-top: 4px; }

        /* Footer */
        .footer {
            font-size: 8px;
            color: #aaa;
            text-align: center;
            margin-top: 28px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        /* Draft watermark */
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

    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">Contrato de Prestación de Servicios</div>
                    <div class="header-sub">{{ $settings['company_legal_name'] ?? 'Luna Avalos' }} · {{ $settings['company_website'] ?? 'lunavalos.com' }}</div>
                </td>
                <td class="header-meta">
                    <div>No. <strong>{{ $contract->contract_number ?? ('#' . $contract->id) }}</strong></div>
                    <div>{{ $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->locale('es')->isoFormat('D MMM YYYY') : now()->locale('es')->isoFormat('D MMM YYYY') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Parties intro -->
    <div class="parties">
        <p>Entre: <strong>{{ $settings['company_legal_name'] ?? 'Luna Avalos' }} / {{ $settings['company_website'] ?? 'lunavalos.com' }}</strong> ("EL PRESTADOR")</p>
        <p>y: <strong>{{ $contract->legal_name }}</strong> ("EL CLIENTE")</p>
        <p>Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA -->
    <h3 class="clause">Primera. Objeto del Contrato</h3>
    <p>EL PRESTADOR se compromete a desarrollar y/o prestar los servicios solicitados por EL CLIENTE conforme a la cotización aceptada No. <strong>{{ $contract->quote?->id ?? '—' }}</strong>, la cual forma parte integral del presente contrato.</p>
    <p><strong>Servicios contratados:</strong></p>
    <ul>
        @foreach($contract->quote?->items ?? [] as $item)
            <li>{{ $item->concept }}{{ $item->description ? ' — ' . $item->description : '' }}</li>
        @endforeach
    </ul>

    <!-- SEGUNDA -->
    <h3 class="clause">Segunda. Monto y Forma de Pago</h3>
    @php $cur = $contract->currency ?? config('currencies.default'); @endphp
    <p>El monto total de los servicios es:</p>
    <ul>
        <li>Subtotal: <strong>@money($contract->subtotal, $cur)</strong></li>
        <li>IVA: <strong>@money($contract->iva_amount, $cur)</strong></li>
        <li>Total: <strong>@money($contract->total_amount, $cur)</strong></li>
    </ul>
    <p><strong>Forma de pago:</strong> Anticipo de <strong>@money($contract->anticipo_amount, $cur)</strong>
    @if($contract->payment_plan_months > 1)
    más <strong>{{ $contract->payment_plan_months }} mensualidades</strong> de <strong>@money($contract->monthly_amount, $cur)</strong>.
    @endif
    Los trabajos iniciarán una vez confirmado el anticipo.</p>

    <!-- TERCERA -->
    <h3 class="clause">Tercera. Tiempos de Entrega</h3>
    <p>El tiempo estimado para la entrega será de <strong>15 días hábiles</strong>, contados a partir de la recepción del anticipo y de los materiales necesarios. Los tiempos podrán modificarse por retrasos del cliente, cambios fuera del alcance inicial o causas de fuerza mayor.</p>

    <!-- CUARTA -->
    <h3 class="clause">Cuarta. Alcance del Servicio</h3>
    <p>El servicio incluye únicamente los conceptos establecidos en la cotización aceptada. Cualquier funcionalidad, modificación, integración o servicio adicional no contemplado será considerado trabajo extra y podrá generar una nueva cotización.</p>

    <!-- QUINTA -->
    <h3 class="clause">Quinta. Cambios y Revisiones</h3>
    <p>EL CLIENTE tendrá derecho a <strong>2 rondas de ajustes menores</strong>, siempre que estén relacionados con el alcance contratado. Cambios que alteren estructura, funcionalidades o nuevos requerimientos podrán generar costos adicionales.</p>

    <!-- SEXTA -->
    <h3 class="clause">Sexta. Propiedad Intelectual</h3>
    <p>Los derechos del trabajo desarrollado serán transferidos a EL CLIENTE una vez liquidado el monto total del contrato. EL PRESTADOR podrá mostrar el proyecto en su portafolio, redes sociales o material promocional, salvo solicitud escrita en contrario.</p>

    <!-- SÉPTIMA -->
    <h3 class="clause">Séptima. Dominios, Hosting y Servicios de Terceros</h3>
    <p>Los costos relacionados con dominio, hosting, servicios de correo, APIs, licencias, plataformas externas y publicidad pagada podrán requerir pagos adicionales y/o renovaciones periódicas. EL PRESTADOR no será responsable por fallas atribuibles a terceros.</p>

    <!-- OCTAVA -->
    <h3 class="clause">Octava. Cancelación</h3>
    <p>En caso de cancelación por parte de EL CLIENTE: el anticipo no será reembolsable y los trabajos ya realizados deberán ser cubiertos proporcionalmente.</p>

    <!-- NOVENA -->
    <h3 class="clause">Novena. Soporte y Garantía</h3>
    <p>EL PRESTADOR brindará soporte técnico por <strong>30 días</strong> posteriores a la entrega final para corrección de errores atribuibles al desarrollo original. No incluye nuevas funcionalidades, modificaciones posteriores ni errores causados por terceros.</p>

    <!-- DÉCIMA -->
    <h3 class="clause">Décima. Aceptación</h3>
    <p>Las partes aceptan el presente contrato y reconocen que la firma electrónica o aceptación digital tendrá validez legal equivalente a firma autógrafa, de conformidad con la legislación aplicable en materia de comercio electrónico.</p>

    <!-- Signatures -->
    <div class="signatures">
        <table class="sig-table">
            <tr>
                <td class="sig-box">
                    <div class="sig-title">El Prestador</div>
                    <p>Nombre: <strong>Luna Avalos</strong></p>
                    <p>Sitio web: {{ $settings['company_website'] ?? 'lunavalos.com' }}</p>
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
                    <p>Representante: {{ $contract->legal_representative }}</p>
                    @endif
                    <p>RFC: {{ $contract->tax_id }}</p>
                    @if($contract->status === 'signed' && $contract->signed_at)
                    <div class="sig-line">
                        Firmado electrónicamente el {{ \Carbon\Carbon::parse($contract->signed_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }}<br>
                        IP de firma: {{ $contract->signature_ip }}
                    </div>
                    @else
                    <div class="sig-line">Pendiente de firma</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ $settings['company_legal_name'] ?? 'Luna Avalos' }}
        @if($settings['company_email'] ?? false) · {{ $settings['company_email'] }} @endif
        · Generado el {{ now()->format('d/m/Y H:i') }}
        @if($contract->status !== 'signed') · DOCUMENTO NO FIRMADO @endif
    </div>

</div>
</body>
</html>
