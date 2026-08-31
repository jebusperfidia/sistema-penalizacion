<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


// 1. El espía: Corre los domingos a las 23:00 hrs para darte el corte de caja y avisarte de tu déficit.
Schedule::command('goals:sunday-alert')->sundays()->at('23:00');

// 2. El verdugo: Corre los lunes a las 23:59 hrs. Cobra las horas de gracia y si no te alcanzó, te multa.
Schedule::command('goals:evaluate')->mondays()->at('23:59');
