<!DOCTYPE html>
<html>
<head>
    <title>Renovación de Servicio</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">
@php
    $logoPath = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $logoPath = \App\Models\Setting::where('key', 'company_logo')->value('value');
        }
    } catch (\Exception $e) {}
@endphp
    
    <div style="text-align: center; margin-bottom: 20px;">
        @if ($logoPath)
            <img src="{{ asset('storage/' . $logoPath) }}" alt="LUNAVALOS" style="max-height: 50px; width: auto; max-width: 100%;">
        @else
            <h2 style="color: #264ab3; margin: 0;">LUNAVALOS</h2>
        @endif
        <p style="color: #666; font-size: 14px; margin-top: 5px;">Aviso de Renovación de Servicios</p>
    </div>

    <p>Hola <strong>{{ $client->contact_name ?: $client->business_name }}</strong>,</p>

    <p>Esperamos que te encuentres muy bien.</p>

    <p>Te escribimos de parte de la administración de <strong>LunAvalos</strong> para notificarte que el periodo de renovación de tu {{ $billingType === 'monthly' ? 'servicio mensual' : 'servicio anual' }} de <strong>{{ $serviceName }}</strong> está próximo a cumplirse.</p>

    <div style="background-color: #f7f9fd; border-left: 4px solid #264ab3; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; font-size: 16px;">
            <strong>Servicio:</strong> {{ $serviceName }}<br>
            <strong>Monto a Pagar:</strong> ${{ number_format($amount, 2) }} MXN<br>
            <strong>Fecha de Vencimiento:</strong> {{ \Carbon\Carbon::parse($client->next_renewal_date)->format('d/m/Y') }}
        </p>
    </div>

    <p>Hemos adjuntado automáticamente a este correo tu recibo en formato PDF para tu control.</p>

    <p>Cualquier duda respecto al pago o al funcionamiento de tus herramientas, no dudes en ponerte en contacto y con gusto te atenderemos.</p>

    <p>Saludos cordiales,<br>
    <strong>El equipo de LunAvalos</strong></p>

    <div style="margin-top: 40px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px;">
        Este es un mensaje automático generado por nuestro sistema de finanzas.
    </div>
</body>
</html>
