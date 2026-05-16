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
Schedule::command('contracts:check-renewals')->dailyAt('08:00');
