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

    /**
     * Evalúa todas las semanas vencidas pendientes de evaluación hasta la semana pasada terminada.
     */
    public static function evaluarSemanasPendientes(): array
    {
        $resultados = [];
        $semanaAnteriorInicio = Carbon::now()->subWeek()->startOfWeek();

        // Encontrar la fecha más antigua de registros o metas para saber desde cuándo evaluar
        $primerLog = TimeLog::min('fecha_registro');
        $primeraMeta = Goal::min('fecha_inicio');

        $fechaMinimaStr = $primerLog ?: ($primeraMeta ?: $semanaAnteriorInicio->toDateString());
        $semanaIter = Carbon::parse($fechaMinimaStr)->startOfWeek();

        // Iteramos de semana en semana hasta la semana inmediatamente anterior a la actual
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

    /**
     * Evalúa una semana específica.
     */
    public static function evaluarSemana(Carbon $inicioSemana, Carbon $finSemana): ?array
    {
        $inicioStr = $inicioSemana->toDateString();
        $finStr = $finSemana->toDateString();

        // Si ya tiene una penalización registrada para esta semana, no volvemos a generar
        $yaTienePenalizacion = Penalty::where('semana_inicio', $inicioStr)->exists();
        if ($yaTienePenalizacion) {
            return null;
        }

        // Si ya tiene una acumulación de superávit registrada para esta semana, ya fue evaluada
        $yaTieneAcumulacion = ExtraHourMovement::where('tipo', 'acumulacion')
            ->where('semana_inicio', $inicioStr)
            ->exists();
        if ($yaTieneAcumulacion) {
            return null;
        }

        $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [$inicioStr, $finStr])
            ->sum('horas_invertidas');

        $horasExtraAplicadas = (float) ExtraHourMovement::horasAplicadasEnSemana($inicioSemana);
        $horasEfectivas = $horasRegistradas + $horasExtraAplicadas;

        $metaGlobal = self::META_GLOBAL_SEMANAL;

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

            $mensaje = "Penalización generada para {$inicioStr} a {$finStr}: Faltaron {$horasFaltantes}h. Multa de \${$multa} MXN.";
            Log::warning($mensaje);

            return [
                'tipo'            => 'penalizacion',
                'semana_inicio'   => $inicioStr,
                'semana_fin'      => $finStr,
                'horas_faltantes' => $horasFaltantes,
                'monto_multa'     => $multa,
                'penalty_id'      => $penalty->id,
            ];
        } else {
            // Si superó la meta directamente con horas registradas, registramos el excedente
            if ($horasRegistradas > $metaGlobal) {
                $excedente = round($horasRegistradas - $metaGlobal, 2);
                ExtraHourMovement::create([
                    'tipo'          => 'acumulacion',
                    'horas'         => $excedente,
                    'semana_inicio' => $inicioStr,
                    'semana_fin'    => $finStr,
                    'descripcion'   => "Superávit de {$excedente} hrs en semana del " . $inicioSemana->format('d/m') . " al " . $finSemana->format('d/m/Y'),
                ]);

                $mensaje = "Superávit registrado para {$inicioStr} a {$finStr}: {$excedente}h a favor.";
                Log::info($mensaje);

                return [
                    'tipo'          => 'superavit',
                    'semana_inicio' => $inicioStr,
                    'semana_fin'    => $finStr,
                    'excedente'     => $excedente,
                ];
            }

            return [
                'tipo'          => 'meta_exacta',
                'semana_inicio' => $inicioStr,
                'semana_fin'    => $finStr,
            ];
        }
    }

    /**
     * Retorna la penalización activa (no pagada) más prioritaria si existe.
     */
    public static function getPenalizacionActiva(): ?Penalty
    {
        return Penalty::where('estado_pago', false)->orderBy('semana_inicio', 'asc')->first();
    }
}
