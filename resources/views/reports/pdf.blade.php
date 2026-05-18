<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
        }

        /* ── HEADER ─────────────────────────────────── */
        .header {
            background: #1e3a8a;
            color: #ffffff;
            padding: 22px 36px;
        }
        .header-table {
            width: 100%;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-table td.meta {
            text-align: right;
        }
        .header-logo img {
            max-height: 48px;
            max-width: 180px;
        }
        .header-logo-fallback {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .header-subtitle {
            font-size: 9px;
            color: rgba(255,255,255,0.65);
            margin-top: 3px;
        }
        .header-period {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
        }
        .header-report-id {
            font-size: 9px;
            color: rgba(255,255,255,0.55);
            margin-top: 3px;
        }

        /* ── CLIENT BANNER ───────────────────────────── */
        .client-banner {
            background: #eff6ff;
            border-left: 5px solid #3b82f6;
            padding: 14px 36px;
        }
        .client-label {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .client-name {
            font-size: 17px;
            font-weight: 800;
            color: #1e3a8a;
            margin-top: 2px;
        }
        .client-contact {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }

        /* ── BODY ────────────────────────────────────── */
        .content { padding: 22px 36px; }

        /* ── SECTION TITLE ───────────────────────────── */
        .section-title {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        /* ── STATS ROW (table-based for DomPDF) ──────── */
        .stats-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 20px;
        }
        .stats-table td {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
            width: 25%;
        }
        .stat-num {
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
        }
        .stat-lbl {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 3px;
        }
        .stat-blue   { color: #1e3a8a; }
        .stat-green  { color: #16a34a; }
        .stat-yellow { color: #d97706; }
        .stat-gray   { color: #6b7280; }

        /* ── SUMMARY BOX ─────────────────────────────── */
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 11px;
            line-height: 1.7;
            color: #374151;
            white-space: pre-wrap;
        }

        /* ── TICKETS SUMMARY TABLE ───────────────────── */
        table.tickets {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        table.tickets thead tr {
            background: #1e3a8a;
            color: #ffffff;
        }
        table.tickets th {
            padding: 7px 9px;
            text-align: left;
            font-weight: 700;
            font-size: 9px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        table.tickets tbody tr:nth-child(even) { background: #f8fafc; }
        table.tickets td {
            padding: 7px 9px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.tickets td.tid { color: #94a3b8; font-weight: 700; white-space: nowrap; }
        table.tickets td.ttitle { font-weight: 600; color: #1e293b; }

        /* STATUS BADGES */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 50px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-nuevos     { background: #dbeafe; color: #1d4ed8; }
        .badge-proceso    { background: #fef3c7; color: #92400e; }
        .badge-revision   { background: #ede9fe; color: #6d28d9; }
        .badge-ajustes    { background: #ffedd5; color: #9a3412; }
        .badge-completado { background: #dcfce7; color: #166534; }

        .p-alta    { color: #ea580c; font-weight: 700; }
        .p-urgente { color: #dc2626; font-weight: 700; }
        .p-baja    { color: #64748b; }
        .p-media   { color: #2563eb; }

        /* ── WORK DATES ───────────────────────────────── */
        .date-cell { font-size: 9px; color: #374151; white-space: nowrap; }
        .date-label { font-size: 8px; color: #94a3b8; display: block; margin-bottom: 1px; }
        .duration-pill {
            display: inline-block;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 8px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        /* ── BITÁCORA (status log) ───────────────────────── */
        .log-section { padding: 8px 14px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .log-title { font-size: 8px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px; }
        .log-entry { margin-bottom: 4px; padding-left: 10px; border-left: 2px solid #e2e8f0; }
        .log-msg { font-size: 9px; color: #374151; }
        .log-time { font-size: 8px; color: #94a3b8; margin-top: 1px; }

        /* ── CONVERSATION SECTION ────────────────────── */
        .ticket-block {
            margin-bottom: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .ticket-block-header {
            background: #1e3a8a;
            color: #ffffff;
            padding: 8px 14px;
        }
        .ticket-block-header table { width: 100%; }
        .ticket-block-header td { vertical-align: middle; }
        .ticket-block-title {
            font-size: 12px;
            font-weight: 700;
        }
        .ticket-block-meta {
            font-size: 9px;
            color: rgba(255,255,255,0.65);
            margin-top: 2px;
        }
        .ticket-block-badge {
            text-align: right;
        }

        /* Messages */
        .message-list { padding: 0; }
        .msg {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .msg:last-child { border-bottom: none; }
        .msg-staff { background: #ffffff; }
        .msg-client { background: #f8fafc; }
        .msg-header-table { width: 100%; margin-bottom: 4px; }
        .msg-author { font-weight: 700; font-size: 10px; color: #1e293b; }
        .msg-author-badge {
            display: inline-block;
            font-size: 7px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 20px;
            margin-left: 4px;
            vertical-align: middle;
        }
        .badge-staff  { background: #dbeafe; color: #1d4ed8; }
        .badge-client { background: #dcfce7; color: #166534; }
        .msg-date { font-size: 9px; color: #94a3b8; text-align: right; }
        .msg-body {
            font-size: 10px;
            color: #374151;
            line-height: 1.6;
        }
        .no-messages {
            padding: 12px 14px;
            font-size: 10px;
            color: #94a3b8;
            font-style: italic;
            text-align: center;
        }

        /* ── NOTES ───────────────────────────────────── */
        .notes-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 11px;
            line-height: 1.7;
            color: #78350f;
            white-space: pre-wrap;
        }

        /* ── FOOTER ──────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 7px 36px;
        }
        .footer-table { width: 100%; }
        .footer-table td { vertical-align: middle; font-size: 8px; color: #94a3b8; }
        .footer-table td.right { text-align: right; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @php
                        $logoBase64 = null;
                        $logoPath = $settings['company_logo'] ?? null;
                        if ($logoPath) {
                            $fullPath = storage_path('app/public/' . $logoPath);
                            if (file_exists($fullPath)) {
                                $mime = mime_content_type($fullPath);
                                if (in_array($mime, ['image/png','image/jpeg','image/gif','image/webp'])) {
                                    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
                                }
                            }
                        }
                    @endphp
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Logo">
                        <div class="header-subtitle">Reporte de Actividades Mensual</div>
                    @else
                        <div class="header-logo-fallback">{{ $settings['company_commercial_name'] ?? 'LunAvalos' }}</div>
                        <div class="header-subtitle">Reporte de Actividades Mensual</div>
                    @endif
                </td>
                <td class="meta">
                    <div class="header-period">{{ $report->period_label }}</div>
                    <div class="header-report-id">REPORTE #{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}</div>
                    <div class="header-report-id">{{ now()->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- CLIENT BANNER -->
    <div class="client-banner">
        <div class="client-label">Cliente</div>
        <div class="client-name">{{ $report->client->business_name }}</div>
        <div class="client-contact">
            {{ $report->client->contact_name }}
            @if($report->client->email) · {{ $report->client->email }} @endif
        </div>
    </div>

    <!-- BODY -->
    <div class="content">

        @php
            // $tickets is now a plain array from the snapshot
            $ticketsArr  = $tickets;
            $total       = count($ticketsArr);
            $completed   = count(array_filter($ticketsArr, fn($t) => ($t['status'] ?? '') === 'Completados'));
            $inProgress  = count(array_filter($ticketsArr, fn($t) => in_array($t['status'] ?? '', ['En Proceso', 'En Revisión', 'Ajustes'])));
            $pending     = $total - $completed - $inProgress;

            // Helper: format a date string
            $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y H:i') : '—';

            // Helper: human duration between two dates
            $calcDuration = function($from, $to) {
                if (!$from || !$to) return null;
                $diff = \Carbon\Carbon::parse($from)->diff(\Carbon\Carbon::parse($to));
                $h = ($diff->days * 24) + $diff->h;
                $m = $diff->i;
                if ($h === 0) return "{$m}m";
                return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
            };
        @endphp

        <!-- STATS — una sola fila con 4 columnas -->
        <table class="stats-table">
            <tr>
                <td>
                    <div class="stat-num stat-blue">{{ $total }}</div>
                    <div class="stat-lbl">Actividades</div>
                </td>
                <td>
                    <div class="stat-num stat-green">{{ $completed }}</div>
                    <div class="stat-lbl">Completadas</div>
                </td>
                <td>
                    <div class="stat-num stat-yellow">{{ $inProgress }}</div>
                    <div class="stat-lbl">En Proceso</div>
                </td>
                <td>
                    <div class="stat-num stat-gray">{{ $pending }}</div>
                    <div class="stat-lbl">Pendientes</div>
                </td>
            </tr>
        </table>

        <!-- EXECUTIVE SUMMARY -->
        @if($report->summary)
            <div class="section-title">Resumen Ejecutivo</div>
            <div class="summary-box">{{ $report->summary }}</div>
        @endif

        <!-- TICKETS SUMMARY TABLE -->
        <div class="section-title">Actividades del Periodo</div>

        @if(count($ticketsArr) > 0)
        <table class="tickets">
            <thead>
                <tr>
                    <th style="width:35px;">#</th>
                    <th>Actividad / Tarea</th>
                    <th style="width:80px;">Servicio</th>
                    <th style="width:70px;">Estatus</th>
                    <th style="width:55px;">Prioridad</th>
                    <th style="width:70px;">Responsable</th>
                    <th style="width:70px;">Inicio</th>
                    <th style="width:70px;">Finalización</th>
                    <th style="width:45px;">Duración</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ticketsArr as $ticket)
                @php
                    $sc = match($ticket['status'] ?? '') {
                        'Nuevos'      => 'badge-nuevos',
                        'En Proceso'  => 'badge-proceso',
                        'En Revisión' => 'badge-revision',
                        'Ajustes'     => 'badge-ajustes',
                        'Completados' => 'badge-completado',
                        default       => 'badge-nuevos',
                    };
                    $pc = match($ticket['priority'] ?? '') {
                        'Alta'    => 'p-alta',
                        'Urgente' => 'p-urgente',
                        'Baja'    => 'p-baja',
                        default   => 'p-media',
                    };
                    $dur = $calcDuration($ticket['work_started_at'] ?? null, $ticket['work_finished_at'] ?? null);
                @endphp
                <tr>
                    <td class="tid">#{{ $ticket['id'] }}</td>
                    <td class="ttitle">{{ $ticket['title'] }}</td>
                    <td>{{ $ticket['client_service']['service_name'] ?? '—' }}</td>
                    <td><span class="badge {{ $sc }}">{{ $ticket['status'] }}</span></td>
                    <td class="{{ $pc }}">{{ $ticket['priority'] }}</td>
                    <td>{{ $ticket['assigned']['name'] ?? 'Sin asignar' }}</td>
                    <td class="date-cell">{{ $fmtDate($ticket['work_started_at'] ?? null) }}</td>
                    <td class="date-cell">{{ $fmtDate($ticket['work_finished_at'] ?? null) }}</td>
                    <td>
                        @if($dur)
                            <span class="duration-pill">{{ $dur }}</span>
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p style="color:#94a3b8; font-style:italic; text-align:center; padding:16px 0;">
                No se registraron actividades en este periodo.
            </p>
        @endif

        @if(count($ticketsArr) > 0)
            <div class="section-title" style="margin-top:26px;">Detalle de Actividades</div>

            @foreach($ticketsArr as $ticket)
            @php
                $sc = match($ticket['status'] ?? '') {
                    'Nuevos'      => 'badge-nuevos',
                    'En Proceso'  => 'badge-proceso',
                    'En Revisión' => 'badge-revision',
                    'Ajustes'     => 'badge-ajustes',
                    'Completados' => 'badge-completado',
                    default       => 'badge-nuevos',
                };
                $dur = $calcDuration($ticket['work_started_at'] ?? null, $ticket['work_finished_at'] ?? null);
                $statusLog = $ticket['status_log'] ?? [];
            @endphp
            <div class="ticket-block">
                <!-- Block header -->
                <div class="ticket-block-header">
                    <table class="ticket-block-header">
                        <tr>
                            <td>
                                <div class="ticket-block-title">{{ $ticket['title'] }}</div>
                                <div class="ticket-block-meta">
                                    #{{ $ticket['id'] }}
                                    @if(!empty($ticket['client_service'])) · {{ $ticket['client_service']['service_name'] }} @endif
                                    @if(!empty($ticket['assigned'])) · {{ $ticket['assigned']['name'] }} @endif
                                </div>
                            </td>
                            <td class="ticket-block-badge">
                                <span class="badge {{ $sc }}">{{ $ticket['status'] }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Work dates + duration -->
                <div style="padding: 8px 14px; background:#f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:33%; padding-right:12px;">
                                <span class="date-label">▶ Inicio de trabajo</span>
                                <span class="date-cell">{{ $fmtDate($ticket['work_started_at'] ?? null) }}</span>
                            </td>
                            <td style="width:33%; padding-right:12px;">
                                <span class="date-label">✓ Finalización</span>
                                <span class="date-cell">{{ $fmtDate($ticket['work_finished_at'] ?? null) }}</span>
                            </td>
                            <td style="width:33%;">
                                <span class="date-label">⏱ Duración total</span>
                                @if($dur)
                                    <span class="duration-pill">{{ $dur }}</span>
                                @else
                                    <span class="date-cell">—</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Status log / bitácora -->
                @if(count($statusLog) > 0)
                <div class="log-section">
                    <div class="log-title">Bitácora de cambios</div>
                    @foreach($statusLog as $entry)
                    <div class="log-entry">
                        <div class="log-msg">{!! strip_tags($entry['message'], '<b><strong><em>') !!}</div>
                        <div class="log-time">{{ \Carbon\Carbon::parse($entry['created_at'])->format('d/m/Y H:i') }}</div>
                    </div>
                    @endforeach
                </div>
                @endif

                <!-- Lienzo del ticket -->
                @php $canvasItems = $ticket['canvas_items'] ?? []; @endphp
                @if(count($canvasItems) > 0)
                <div class="log-section">
                    <div class="log-title">Lienzo · {{ count($canvasItems) }} {{ count($canvasItems) === 1 ? 'pieza' : 'piezas' }}</div>
                    <table style="width:100%; border-collapse:collapse;">
                        @foreach(array_chunk($canvasItems, 3) as $row)
                        <tr>
                            @foreach($row as $ci)
                            @php
                                $imgPath = !empty($ci['file_path']) ? public_path('storage/' . $ci['file_path']) : null;
                                $isImg   = ($ci['type'] ?? null) === 'image' && $imgPath && file_exists($imgPath);
                                $apStat  = $ci['approval_status'] ?? 'pending';
                                $apLabel = $apStat === 'approved' ? 'Aprobado' : ($apStat === 'changes_requested' ? 'Cambios' : 'Pendiente');
                                $apBg    = $apStat === 'approved' ? '#dcfce7' : ($apStat === 'changes_requested' ? '#ffedd5' : '#f1f5f9');
                                $apCol   = $apStat === 'approved' ? '#166534' : ($apStat === 'changes_requested' ? '#9a3412' : '#475569');
                            @endphp
                            <td style="width:33%; padding:4px; vertical-align:top;">
                                <div style="border:1px solid #e2e8f0; border-radius:6px; padding:6px; background:#fff;">
                                    @if($isImg)
                                        <img src="{{ $imgPath }}" style="width:100%; max-height:120px; object-fit:cover; border-radius:4px;" />
                                    @else
                                        <div style="height:80px; background:#f1f5f9; border-radius:4px; text-align:center; font-size:9px; color:#94a3b8; padding-top:32px;">
                                            {{ strtoupper($ci['type'] ?? 'archivo') }}
                                        </div>
                                    @endif
                                    @if(!empty($ci['caption']))
                                        <div style="font-size:9px; color:#334155; margin-top:4px;">{{ $ci['caption'] }}</div>
                                    @endif
                                    <div style="margin-top:4px;">
                                        <span style="display:inline-block; font-size:8px; font-weight:700; background:{{ $apBg }}; color:{{ $apCol }}; padding:2px 6px; border-radius:3px;">{{ $apLabel }}</span>
                                        @php $pinCount = count($ci['pins'] ?? []) + array_sum(array_map(fn($c) => count($c['pins'] ?? []), $ci['children'] ?? [])); @endphp
                                        @if($pinCount > 0)
                                            <span style="font-size:8px; color:#64748b; margin-left:4px;">{{ $pinCount }} comentario{{ $pinCount === 1 ? '' : 's' }}</span>
                                        @endif
                                        @if(!empty($ci['children']) && count($ci['children']) > 0)
                                            <span style="font-size:8px; color:#64748b; margin-left:4px;">+{{ count($ci['children']) }} stack</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            @endforeach
                            @for($i = count($row); $i < 3; $i++)
                                <td style="width:33%;"></td>
                            @endfor
                        </tr>
                        @endforeach
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        @endif

        <!-- DOCUMENTOS DEL CLIENTE -->
        @if(!empty($clientAssets) && count($clientAssets) > 0)
            <div class="section-title">Documentos del cliente</div>
            <table style="width:100%; border-collapse:collapse;">
                @foreach(array_chunk($clientAssets, 2) as $row)
                <tr>
                    @foreach($row as $a)
                    @php
                        $aPath = !empty($a['file_path']) ? public_path('storage/' . $a['file_path']) : null;
                        $isLogo = ($a['kind'] ?? null) === 'logo' && $aPath && file_exists($aPath);
                    @endphp
                    <td style="width:50%; padding:4px; vertical-align:top;">
                        <div style="border:1px solid #e2e8f0; border-radius:6px; padding:8px; background:#fff;">
                            <table style="width:100%;"><tr>
                                <td style="width:48px; vertical-align:top;">
                                    @if($isLogo)
                                        <img src="{{ $aPath }}" style="width:40px; height:40px; object-fit:contain; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;" />
                                    @elseif(($a['kind'] ?? null) === 'color_palette' && !empty($a['value']['colors']))
                                        <table style="width:40px; height:40px; border-collapse:collapse; border:1px solid #e2e8f0;"><tr>
                                            @foreach(array_slice($a['value']['colors'], 0, 4) as $c)
                                                <td style="background: {{ $c }}; width:10px; height:40px;"></td>
                                            @endforeach
                                        </tr></table>
                                    @else
                                        <div style="width:40px; height:40px; background:#f1f5f9; border-radius:4px;"></div>
                                    @endif
                                </td>
                                <td style="vertical-align:top; padding-left:8px;">
                                    <div style="font-size:11px; font-weight:700; color:#1e293b;">{{ $a['label'] ?? '' }}</div>
                                    <div style="font-size:9px; color:#94a3b8; text-transform:uppercase;">{{ $a['kind'] ?? '' }}</div>
                                    @if(!empty($a['url']))
                                        <div style="font-size:9px; color:#264ab3; word-break:break-all;">{{ $a['url'] }}</div>
                                    @elseif(!empty($a['file_name']))
                                        <div style="font-size:9px; color:#475569;">{{ $a['file_name'] }}</div>
                                    @elseif(($a['kind'] ?? null) === 'note' && !empty($a['value']['text']))
                                        <div style="font-size:9px; color:#475569; font-style:italic;">{{ \Illuminate\Support\Str::limit($a['value']['text'], 120) }}</div>
                                    @elseif(($a['kind'] ?? null) === 'typography' && !empty($a['value']['family']))
                                        <div style="font-size:9px; color:#475569;">{{ $a['value']['family'] }}</div>
                                    @endif
                                </td>
                            </tr></table>
                        </div>
                    </td>
                    @endforeach
                    @if(count($row) < 2)<td style="width:50%;"></td>@endif
                </tr>
                @endforeach
            </table>
        @endif

        <!-- NOTES -->
        @if($report->notes)
            <div class="section-title">Observaciones y Notas</div>
            <div class="notes-box">{{ $report->notes }}</div>
        @endif

    </div>

    <!-- FOOTER -->
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>{{ $settings['company_commercial_name'] ?? 'LunAvalos' }} · Generado el {{ now()->format('d/m/Y H:i') }}</td>
                <td class="right">Página <span style="font-weight:700;">{{-- DomPDF page counter --}}</span></td>
            </tr>
        </table>
    </div>

</body>
</html>
