<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeLog;
use App\Models\ExtraHourMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SundayGraceAlert extends Command
{
    protected $signature = 'goals:sunday-alert';
    protected $description = 'Revisa las horas el domingo en la noche y avisa si se entra en periodo de gracia.';

    public function handle()
    {
        $fechaFin = Carbon::today(); // Hoy domingo
        $fechaInicio = $fechaFin->copy()->startOfWeek();

        $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->where('es_reposicion', false)
            ->sum('horas_invertidas');

        $horasExtraAplicadas = (float) ExtraHourMovement::horasAplicadasEnSemana($fechaInicio);
        $total = $horasRegistradas + $horasExtraAplicadas;

        if ($total < 16.0) {
            $faltan = 16.0 - $total;
            $mensaje = "🚨 ALERTA DE GRACIA: Cierras el domingo con {$total}h. Te faltan {$faltan}h. Tienes mañana lunes para salvarte de la multa.";
            $this->warn($mensaje);
            Log::warning($mensaje);
            // Aquí en un futuro podrías conectarle un bot de Telegram o un correo para que te llegue al cel.
        } else {
            $this->info("Semana asegurada con {$total}h. ¡A descansar!");
            Log::info("Corte dominical: Semana asegurada con {$total}h.");
        }
    }
}
