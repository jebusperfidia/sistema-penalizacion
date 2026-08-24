<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ExtraHourMovement extends Model
{
    protected $fillable = [
        'tipo',
        'horas',
        'semana_inicio',
        'semana_fin',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'horas' => 'decimal:2',
            'semana_inicio' => 'date',
            'semana_fin' => 'date',
        ];
    }

    /**
     * Obtiene el saldo neto disponible de horas extras acumuladas.
     */
    public static function saldoDisponible(): float
    {
        // Auto-sincronizamos excedentes de semanas pasadas antes de consultar el saldo
        static::sincronizarExcedentesHistoricos();

        $acumuladas = (float) static::where('tipo', 'acumulacion')->sum('horas');
        $aplicadas = (float) static::where('tipo', 'aplicacion')->sum('horas');

        return max(0.0, round($acumuladas - $aplicadas, 2));
    }

    /**
     * Obtiene las horas extra aplicadas a una semana en específico.
     */
    public static function horasAplicadasEnSemana($semanaInicio): float
    {
        $fecha = is_string($semanaInicio) ? $semanaInicio : Carbon::parse($semanaInicio)->toDateString();

        return (float) static::where('tipo', 'aplicacion')
            ->where('semana_inicio', $fecha)
            ->sum('horas');
    }

    /**
     * Revisa todas las semanas pasadas completadas. Si en alguna se registraron más de 16 horas
     * y aún no se ha registrado su crédito de acumulación, lo crea automáticamente.
     */
    public static function sincronizarExcedentesHistoricos(): void
    {
        $primerLog = TimeLog::orderBy('fecha_registro', 'asc')->first();
        if (!$primerLog) {
            return;
        }

        $semanaActualInicio = Carbon::now()->startOfWeek();
        $semanaPuntero = Carbon::parse($primerLog->fecha_registro)->startOfWeek();

        // Iterar hasta la semana anterior a la actual
        while ($semanaPuntero->lt($semanaActualInicio)) {
            $semanaFin = $semanaPuntero->copy()->endOfWeek();
            $inicioStr = $semanaPuntero->toDateString();
            $finStr = $semanaFin->toDateString();

            $horasRegistradas = (float) TimeLog::whereBetween('fecha_registro', [$inicioStr, $finStr])
                ->sum('horas_invertidas');

            if ($horasRegistradas > 16.0) {
                $excedente = round($horasRegistradas - 16.0, 2);

                // Comprobamos si ya existe el registro de acumulación para esta semana
                $existe = static::where('tipo', 'acumulacion')
                    ->where('semana_inicio', $inicioStr)
                    ->exists();

                if (!$existe) {
                    static::create([
                        'tipo' => 'acumulacion',
                        'horas' => $excedente,
                        'semana_inicio' => $inicioStr,
                        'semana_fin' => $finStr,
                        'descripcion' => "Superávit de {$excedente} hrs (Total: {$horasRegistradas} hrs) en semana del " . $semanaPuntero->format('d/m') . " al " . $semanaFin->format('d/m/Y'),
                    ]);
                }
            }

            $semanaPuntero->addWeek();
        }
    }
}
