<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


// El espía: Corre los domingos a las 23:00 hrs para darte el corte de caja y avisarte de tu déficit.
Schedule::command('goals:sunday-alert')->sundays()->at('23:00');

// Corre el Martes a las 00:01 hrs (Justo cuando se acaba tu día de gracia)
Schedule::command('goals:evaluate')->tuesdays()->at('00:01');
