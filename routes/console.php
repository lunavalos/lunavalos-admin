<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('quotes:check-followup')->daily();
Schedule::command('quotes:expire')->daily();
Schedule::command('tickets:auto-close-in-review')->daily();
Schedule::command('tickets:auto-archive-completed')->daily();
Schedule::command('contracts:check-renewals')->dailyAt('08:00');
Schedule::command('recurring:open-cycles')->monthlyOn(1, '02:00');
Schedule::command('social:dispatch-scheduled')->everyFiveMinutes();
Schedule::command('social:fetch-insights')->everySixHours();
Schedule::command('social:fetch-account-stats')->dailyAt('03:00');
// Las URLs de foto de perfil de Meta caducan a las pocas horas: sin refrescarlas
// la interfaz acaba mostrando el avatar de respaldo en vez de la foto real.
Schedule::command('social:refresh-avatars')->dailyAt('03:30');

// FX: Banxico publica el tipo de cambio FIX hábil ~12:00 CDMX.
// Lo jalamos a las 13:30 (zona MX) para tener la tasa del día disponible
// y otra vez a las 07:00 del día siguiente como red de seguridad.
Schedule::command('fx:fetch')->dailyAt('13:30')->timezone('America/Mexico_City');
Schedule::command('fx:fetch')->dailyAt('07:00')->timezone('America/Mexico_City');

// Firmas de correo: el driver `file` no borra la caché vencida y la de firmas
// se renueva con cada cambio del admin. Lo vigente dura minutos, así que todo
// lo de más de un día en su carpeta es basura (a lo sumo, una consulta extra).
Artisan::command('signatures:prune-cache', function () {
    $store = config('cache.stores.' . config('signatures.cache_store'));

    if (($store['driver'] ?? null) !== 'file' || ! is_dir($store['path'])) {
        return $this->info('La caché de firmas no usa archivos; no hay nada que purgar.');
    }

    $limite = now()->subDay()->getTimestamp();
    $borrados = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($store['path'], FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->isFile() && $archivo->getMTime() < $limite && @unlink($archivo->getPathname())) {
            $borrados++;
        }
    }

    $this->info("Archivos de caché de firmas borrados: {$borrados}");
})->purpose('Borra la caché vencida de las imágenes de firmas de correo');

Schedule::command('signatures:prune-cache')->dailyAt('04:00');
