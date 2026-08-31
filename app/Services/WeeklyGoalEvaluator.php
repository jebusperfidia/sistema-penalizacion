<?php

namespace App\Services;

use App\Models\TimeLog;
use App\Models\Penalty;
use App\Models\ExtraHourMovement;
use App\Models\Goal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeeklyGoalEvaluator
{
    const META_GLOBAL_SEMANAL = 16.0;
    const MONTO_POR_HORA_FALTANTE = 100.0;

    public static function evaluarSemanasPendientes(): array
    {
        $resultados = [];
        // Al correr el lunes, esto evalúa hasta la semana que terminó ayer domingo
        $semanaAnteriorInicio = Carbon::now()->subWeek()->startOfWeek();

        $primerLog = TimeLog::min('fecha_registro');
        $primeraMeta = Goal::min('fecha_inicio');

        $fechaMinimaStr = $primerLog ?: ($primeraMeta ?: $semanaAnteriorInicio->toDateString());
        $semanaIter = Carbon::parse($fechaMinimaStr)->startOfWeek();

        while ($semanaIter->lessThanOrEqualTo($semanaAnteriorInicio)) {
            $inicioSemana = $semanaIter->copy()->startOfWeek();
            $finSemana = $semanaIter->copy()->endOfWeek();

            $resultado = self::evaluarSemana($inicioSemana, $finSemana);
            if ($resultado) {
                $resultados[] = $resultado;
            }

            $semanaIter->addWeek();
        }

        return $resultados;
    }

    public static function evaluarSemana(Carbon $inicioSemana, Carbon $finSemana): ?array
    {
        $inicioStr = $inicioSemana->toDateString();
        $finStr = $finSemana->toDateString();

        if (Penalty::where('semana_inicio', $inicioStr)->exists()) return null;
        if (ExtraHourMovement::where('tipo', 'acumulacion')->where('semana_inicio', $inicioStr)->exists()) return null;

        // 1. Horas normales de Lunes a Domingo (que no se usaron para pagar deudas anteriores)
        $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [$inicioStr, $finStr])
            ->where('es_reposicion', false)
            ->sum('horas_invertidas');

        // 2. Horas de rescate: Las que se hicieron el lunes siguiente y ya están marcadas
        $lunesSiguiente = $finSemana->copy()->addDay()->toDateString();
        $horasReposicionExistentes = (float) TimeLog::where('fecha_registro', $lunesSiguiente)
            ->where('es_reposicion', true)
            ->sum('horas_invertidas');

        $horasExtraAplicadas = (float) ExtraHourMovement::horasAplicadasEnSemana($inicioSemana);

        $horasEfectivas = $horasRegistradas + $horasReposicionExistentes + $horasExtraAplicadas;
        $metaGlobal = self::META_GLOBAL_SEMANAL;

        // 3. ¡EL SALVAVIDAS DEL LUNES! Si aún faltan horas, intentamos cobrarnos de los logs de este lunes
        if ($horasEfectivas < $metaGlobal) {
            $logsLunes = TimeLog::where('fecha_registro', $lunesSiguiente)
                ->where('es_reposicion', false)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($logsLunes as $log) {
                if ($horasEfectivas >= $metaGlobal) break;

                // Nos comemos el log completo para pagar la deuda
                $log->es_reposicion = true;
                $log->save();

                $horasEfectivas += $log->horas_invertidas;
                Log::info("Salvavidas activado: Log de {$log->horas_invertidas}h del lunes {$lunesSiguiente} consumido para la semana del {$inicioStr}.");
            }
        }

        // 4. Veredicto Final
        if ($horasEfectivas < $metaGlobal) {
            $horasFaltantes = round($metaGlobal - $horasEfectivas, 2);
            $multa = $horasFaltantes * self::MONTO_POR_HORA_FALTANTE;

            $penalty = Penalty::create([
                'semana_inicio'   => $inicioStr,
                'semana_fin'      => $finStr,
                'horas_faltantes' => $horasFaltantes,
                'monto_multa'     => $multa,
                'estado_pago'     => false,
            ]);

            Log::warning("Penalización FIRME para {$inicioStr}: Faltaron {$horasFaltantes}h. Multa: \${$multa}.");

            return [
                'tipo'            => 'penalizacion',
                'semana_inicio'   => $inicioStr,
                'semana_fin'      => $finStr,
                'horas_faltantes' => $horasFaltantes,
                'monto_multa'     => $multa,
                'penalty_id'      => $penalty->id,
            ];
        } elseif ($horasEfectivas > $metaGlobal) {
            $excedente = round($horasEfectivas - $metaGlobal, 2);
            ExtraHourMovement::create([
                'tipo'          => 'acumulacion',
                'horas'         => $excedente,
                'semana_inicio' => $inicioStr,
                'semana_fin'    => $finStr,
                'descripcion'   => "Superávit de {$excedente} hrs.",
            ]);

            return [
                'tipo' => 'superavit',
                'semana_inicio' => $inicioStr,
                'semana_fin' => $finStr,
                'excedente' => $excedente,
            ];
        }

        return ['tipo' => 'meta_exacta', 'semana_inicio' => $inicioStr, 'semana_fin' => $finStr];
    }

    public static function getPenalizacionActiva(): ?Penalty
    {
        return Penalty::where('estado_pago', false)->orderBy('semana_inicio', 'asc')->first();
    }
}
