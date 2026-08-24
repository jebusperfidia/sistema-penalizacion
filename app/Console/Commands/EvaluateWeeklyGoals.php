<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeLog;
use App\Models\Penalty;
use App\Models\ExtraHourMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
            Log::warning($mensaje);
            return;
        }

        $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [
            $fechaInicio->toDateString(),
            $fechaFin->toDateString()
        ])->sum('horas_invertidas');

        $horasExtraAplicadas = (float) ExtraHourMovement::horasAplicadasEnSemana($fechaInicio);
        $horasEfectivas = $horasRegistradas + $horasExtraAplicadas;

        $metaGlobal = 16.0;

        if ($horasEfectivas < $metaGlobal) {
            $horasFaltantes = $metaGlobal - $horasEfectivas;
            $multa = $horasFaltantes * 100;

            Penalty::create([
                'semana_inicio' => $fechaInicio->toDateString(),
                'semana_fin' => $fechaFin->toDateString(),
                'horas_faltantes' => $horasFaltantes,
                'monto_multa' => $multa,
                'estado_pago' => false,
            ]);

            $mensaje = "¡Castigo generado! Faltaron {$horasFaltantes} horas (Registradas: {$horasRegistradas}h, Extras aplicadas: {$horasExtraAplicadas}h). Multa de \${$multa} MXN.";
            $this->error($mensaje);
            Log::error($mensaje);
        } else {
            // Si las horas registradas directamente superaron la meta, registramos el excedente en la bolsa
            if ($horasRegistradas > $metaGlobal) {
                $excedente = round($horasRegistradas - $metaGlobal, 2);
                $yaRegistrado = ExtraHourMovement::where('tipo', 'acumulacion')
                    ->where('semana_inicio', $fechaInicio->toDateString())
                    ->exists();

                if (!$yaRegistrado) {
                    ExtraHourMovement::create([
                        'tipo' => 'acumulacion',
                        'horas' => $excedente,
                        'semana_inicio' => $fechaInicio->toDateString(),
                        'semana_fin' => $fechaFin->toDateString(),
                        'descripcion' => "Superávit de {$excedente} hrs (Total: {$horasRegistradas} hrs) en semana del " . $fechaInicio->format('d/m') . " al " . $fechaFin->format('d/m/Y'),
                    ]);
                }
            }

            $mensaje = "¡Semana superada! Total de horas efectivas: {$horasEfectivas} (Registradas: {$horasRegistradas}h, Extras aplicadas: {$horasExtraAplicadas}h). Sin penalizaciones.";
            $this->info($mensaje);
            Log::info($mensaje);
        }
    }
}
