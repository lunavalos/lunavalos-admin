<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Tickets — {{ $teamMember->name }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 20px; background: #f7f9fd;">
@php
    $statusColors = [
        'Nuevos'      => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1d4ed8'],
        'En Proceso'  => ['bg' => '#fefce8', 'border' => '#fde047', 'text' => '#a16207'],
        'En Revisión' => ['bg' => '#faf5ff', 'border' => '#c084fc', 'text' => '#7e22ce'],
        'Ajustes'     => ['bg' => '#fff7ed', 'border' => '#fb923c', 'text' => '#c2410c'],
        'Completados' => ['bg' => '#f0fdf4', 'border' => '#86efac', 'text' => '#15803d'],
    ];

    $priorityColors = [
        'Baja'    => '#6b7280',
        'Media'   => '#2563eb',
        'Alta'    => '#ea580c',
        'Urgente' => '#dc2626',
    ];

    $statusGroups = $tickets->groupBy('status');
    $totalTickets = $tickets->count();
@endphp

<div style="background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); overflow: hidden;">

    {{-- Header --}}
    <div style="background: linear-gradient(135deg, #264ab3 0%, #1a3380 100%); padding: 32px 40px; text-align: center;">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 70px; width: auto; max-width: 260px; display: block; margin: 0 auto 16px;"/>
        @else
            <h2 style="color: white; margin: 0 0 16px; font-size: 22px; letter-spacing: 2px;">{{ strtoupper($companyName) }}</h2>
        @endif
        <h1 style="color: white; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">
            Reporte de Tickets Asignados
        </h1>
        <p style="color: rgba(255,255,255,0.75); margin: 8px 0 0; font-size: 14px;">
            {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
        </p>
    </div>

    <div style="padding: 32px 40px;">

        {{-- Greeting --}}
        <p style="font-size: 15px; color: #374151; margin: 0 0 24px;">
            Este es un resumen automático de los tickets asignados a <strong>{{ $teamMember->name }}</strong>
            en el período indicado. Total de tickets encontrados en el rango: <strong>{{ $totalTickets }}</strong>.
        </p>

        {{-- Summary badges --}}
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px;">
            @foreach ($statusGroups as $status => $group)
                @php $colors = $statusColors[$status] ?? ['bg' => '#f3f4f6', 'border' => '#d1d5db', 'text' => '#374151']; @endphp
                <div style="background: {{ $colors['bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 8px; padding: 10px 18px; text-align: center; min-width: 90px;">
                    <div style="font-size: 22px; font-weight: 800; color: {{ $colors['text'] }};">{{ $group->count() }}</div>
                    <div style="font-size: 11px; color: {{ $colors['text'] }}; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">{{ $status }}</div>
                </div>
            @endforeach
        </div>

        {{-- Tickets by status --}}
        @foreach ($statusGroups as $status => $group)
            @php $colors = $statusColors[$status] ?? ['bg' => '#f3f4f6', 'border' => '#d1d5db', 'text' => '#374151']; @endphp

            <div style="margin-bottom: 28px;">
                <h2 style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: {{ $colors['text'] }}; background: {{ $colors['bg'] }}; border-left: 4px solid {{ $colors['border'] }}; padding: 8px 14px; margin: 0 0 12px; border-radius: 0 6px 6px 0;">
                    {{ $status }} ({{ $group->count() }})
                </h2>

                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="text-align: left; padding: 8px 10px; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">#</th>
                            <th style="text-align: left; padding: 8px 10px; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Título</th>
                            <th style="text-align: left; padding: 8px 10px; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Cliente</th>
                            <th style="text-align: center; padding: 8px 10px; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Prioridad</th>
                            <th style="text-align: center; padding: 8px 10px; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Entrega</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group as $i => $ticket)
                        <tr style="{{ $i % 2 === 0 ? 'background: white;' : 'background: #f9fafb;' }}">
                            <td style="padding: 9px 10px; border-bottom: 1px solid #f3f4f6; color: #9ca3af; font-weight: 700;">#{{ $ticket->id }}</td>
                            <td style="padding: 9px 10px; border-bottom: 1px solid #f3f4f6; color: #111827; font-weight: 600; max-width: 220px;">{{ $ticket->title }}</td>
                            <td style="padding: 9px 10px; border-bottom: 1px solid #f3f4f6; color: #4b5563; font-size: 12px;">
                                {{ $ticket->client?->business_name ?? ($ticket->creator?->client?->business_name ?? '—') }}
                            </td>
                            <td style="padding: 9px 10px; border-bottom: 1px solid #f3f4f6; text-align: center;">
                                <span style="font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; background: {{ ($priorityColors[$ticket->priority] ?? '#6b7280') }}22; color: {{ $priorityColors[$ticket->priority] ?? '#6b7280' }}; text-transform: uppercase;">
                                    {{ $ticket->priority }}
                                </span>
                            </td>
                            <td style="padding: 9px 10px; border-bottom: 1px solid #f3f4f6; text-align: center; color: {{ $ticket->due_date ? '#ea580c' : '#d1d5db' }}; font-size: 12px; font-weight: {{ $ticket->due_date ? '700' : '400' }};">
                                {{ $ticket->due_date ? \Carbon\Carbon::parse($ticket->due_date)->format('d/m/Y') : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        @if ($tickets->isEmpty())
            <div style="text-align: center; padding: 40px; color: #9ca3af; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;">
                <p style="margin: 0; font-size: 15px;">No se encontraron tickets asignados a <strong>{{ $teamMember->name }}</strong> en este período.</p>
            </div>
        @endif

    </div>

    {{-- Footer --}}
    <div style="background: #f8fafc; border-top: 1px solid #e5e7eb; padding: 20px 40px; text-align: center;">
        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
            Este reporte fue generado automáticamente por el sistema de gestión de {{ $companyName }}.<br>
            Período: <strong>{{ \Carbon\Carbon::parse($dateFrom)->format('d \d\e F Y') }}</strong> al <strong>{{ \Carbon\Carbon::parse($dateTo)->format('d \d\e F Y') }}</strong>
        </p>
    </div>
</div>

</body>
</html>
