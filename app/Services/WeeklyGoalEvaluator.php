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
        // Evaluamos siempre hasta la semana que acaba de terminar
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

        // 1. Horas normales de Lunes a Domingo
        $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [$inicioStr, $finStr])
            ->where('es_reposicion', false)
            ->sum('horas_invertidas');

        // 2. Horas de rescate: Las que se hicieron el lunes siguiente y se usaron para pagar
        $lunesSiguiente = $finSemana->copy()->addDay()->toDateString();
        $horasReposicionExistentes = (float) TimeLog::where('fecha_registro', $lunesSiguiente)
            ->where('es_reposicion', true)
            ->sum('horas_invertidas');

        $horasExtraAplicadas = (float) ExtraHourMovement::horasAplicadasEnSemana($inicioSemana);

        $horasEfectivas = $horasRegistradas + $horasReposicionExistentes + $horasExtraAplicadas;
        $metaGlobal = self::META_GLOBAL_SEMANAL;

        // Identificar si la semana que estamos revisando es exactamente la semana pasada
        $esSemanaPasada = $inicioStr === Carbon::now()->subWeek()->startOfWeek()->toDateString();

        if ($horasEfectivas < $metaGlobal) {
            $horasFaltantes = round($metaGlobal - $horasEfectivas, 2);

            // ESCUDO DE LUNES: Si es Lunes y estamos evaluando la semana pasada, NO multamos aún.
            if (Carbon::now()->isMonday() && $esSemanaPasada) {
                return [
                    'tipo'            => 'gracia',
                    'semana_inicio'   => $inicioStr,
                    'semana_fin'      => $finStr,
                    'horas_faltantes' => $horasFaltantes,
                ];
            }

            // Si es martes en adelante, o es una deuda muy vieja... cae la guillotina.
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
        $penalizacion = Penalty::where('estado_pago', false)->orderBy('semana_inicio', 'asc')->first();

        // ESCUDO: Si hoy es lunes y la multa que encontró es de la semana que acaba de terminar,
        // la ignoramos para no bloquear el sistema y permitir la captura de reposición.
        if ($penalizacion && Carbon::now()->isMonday()) {
            $semanaPasada = Carbon::now()->subWeek()->startOfWeek()->toDateString();
            if ($penalizacion->semana_inicio === $semanaPasada) {
                return null;
            }
        }

        return $penalizacion;
    }
}
