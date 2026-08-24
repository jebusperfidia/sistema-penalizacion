<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeeklyGoalEvaluator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EvaluateWeeklyGoals extends Command
{
    protected $signature = 'goals:evaluate';
    protected $description = 'Evalúa las horas registradas en las semanas concluidas y genera penalizaciones si no se cumple el mínimo global.';

    public function handle()
    {
        $this->info('Evaluando semanas vencidas...');
        $resultados = WeeklyGoalEvaluator::evaluarSemanasPendientes();

        if (empty($resultados)) {
            $this->info('No hay semanas pendientes por evaluar.');
            return;
        }

        foreach ($resultados as $res) {
            if ($res['tipo'] === 'penalizacion') {
                $this->error("¡Penalización generada! Semana {$res['semana_inicio']} a {$res['semana_fin']}: Faltaron {$res['horas_faltantes']}h. Multa: \${$res['monto_multa']} MXN.");
            } elseif ($res['tipo'] === 'superavit') {
                $this->info("Superávit en semana {$res['semana_inicio']} a {$res['semana_fin']}: {$res['excedente']}h agregadas a la bolsa.");
            } else {
                $this->info("Semana {$res['semana_inicio']} a {$res['semana_fin']} cumplida exactamente sin penalización.");
            }
        }
    }
}
