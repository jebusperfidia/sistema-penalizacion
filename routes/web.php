<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', 'login');

Route::middleware(['auth'])->group(function () {

    // 1. RUTA EXENTA DEL BLOQUEO (Para poder pagar la multa)
    Volt::route('liquidacion', 'penalties-liquidation')->name('penalties.liquidation');

    // 2. RUTAS PROTEGIDAS POR EL MIDDLEWARE DE BLOQUEO
    Route::middleware(['check.penalties'])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');


        Volt::route('materias', 'category-manager')->name('materias');
        Volt::route('metas', 'goal-manager')->name('metas');
        Volt::route('registro', 'time-logger')->name('registro');

        Route::redirect('settings', 'settings/profile');
        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });
});

require __DIR__ . '/auth.php';
