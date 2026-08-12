<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeLog;
use App\Models\Penalty;
use Carbon\Carbon;

class EvaluateWeeklyGoals extends Command
{
    protected $signature = 'goals:evaluate';
    protected $description = 'Evalúa las horas totales registradas en la semana y genera penalizaciones si no se cumple el mínimo global.';

    public function handle()
    {
        $fechaFin = Carbon::today();
        $fechaInicio = $fechaFin->copy()->startOfWeek();

        // CANDADO: Validar si ya se te castigó esta semana para no duplicar la multa en el siguiente intento
        $yaCastigado = Penalty::where('semana_inicio', $fechaInicio->toDateString())->exists();

        if ($yaCastigado) {
            $this->info('Esta semana ya fue evaluada y tiene una penalización activa. Omitiendo.');
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

            $this->error("¡Castigo generado! Faltaron {$horasFaltantes} horas. Multa de \${$multa} MXN.");
        } else {
            // Nota: Si llegas a la meta, el comando no guarda nada, solo ignora.
            // Si el comando vuelve a correr a la siguiente hora, volverá a evaluar y verá que sigues cumpliendo.
            $this->info("¡Semana superada! Total de horas: {$horasTotales}. Sin penalizaciones.");
        }
    }
}
