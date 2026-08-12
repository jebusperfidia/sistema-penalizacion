<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


// Ejecutar todos los días, entre las 18:00 y las 21:00 hrs, cada hora
Schedule::command('goals:evaluate')
    ->daily()
    ->between('18:00', '21:00')
    ->hourly();
