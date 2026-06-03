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

// FX: Banxico publica el tipo de cambio FIX hábil ~12:00 CDMX.
// Lo jalamos a las 13:30 (zona MX) para tener la tasa del día disponible
// y otra vez a las 07:00 del día siguiente como red de seguridad.
Schedule::command('fx:fetch')->dailyAt('13:30')->timezone('America/Mexico_City');
Schedule::command('fx:fetch')->dailyAt('07:00')->timezone('America/Mexico_City');
