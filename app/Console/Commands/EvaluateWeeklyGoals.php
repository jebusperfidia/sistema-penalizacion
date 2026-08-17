<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeLog;
use App\Models\Penalty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // <-- Agregamos esta línea

class EvaluateWeeklyGoals extends Command
{
    protected $signature = 'goals:evaluate';
    protected $description = 'Evalúa las horas totales registradas en la semana y genera penalizaciones si no se cumple el mínimo global.';

    public function handle()
    {
        $fechaFin = Carbon::today();
        $fechaInicio = $fechaFin->copy()->startOfWeek();

        // CANDADO: Validar si ya se te castigó esta semana
        $yaCastigado = Penalty::where('semana_inicio', $fechaInicio->toDateString())->exists();

        if ($yaCastigado) {
            $mensaje = 'Esta semana ya fue evaluada y tiene una penalización activa. Omitiendo.';
            $this->info($mensaje);
            Log::warning($mensaje); // Guarda en storage/logs/laravel.log
            return;
        }

        $horasTotales = TimeLog::whereBetween('fecha_registro', [
            $fechaInicio->toDateString(),
            $fechaFin->toDateString()
        ])->sum('horas_invertidas');

        $metaGlobal = 16.0;

        if ($horasTotales < $metaGlobal) {
            $horasFaltantes = $metaGlobal - $horasTotales;
            $multa = $horasFaltantes * 100;

            Penalty::create([
                'semana_inicio' => $fechaInicio->toDateString(),
                'semana_fin' => $fechaFin->toDateString(),
                'horas_faltantes' => $horasFaltantes,
                'monto_multa' => $multa,
                'estado_pago' => false,
            ]);

            $mensaje = "¡Castigo generado! Faltaron {$horasFaltantes} horas. Multa de \${$multa} MXN.";
            $this->error($mensaje);
            Log::error($mensaje); // Guarda el castigo en el log
        } else {
            $mensaje = "¡Semana superada! Total de horas: {$horasTotales}. Sin penalizaciones.";
            $this->info($mensaje);
            Log::info($mensaje); // Guarda la victoria en el log
        }
    }
}
