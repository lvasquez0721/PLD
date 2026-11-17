<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Banxico publica la FIX a partir de las 12:00 horas (America/Mexico_City).
// Se sincronizan los últimos días para recuperar fechas faltantes.
Schedule::command('tipocambio:actualizar')
    ->dailyAt('12:30')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping();
