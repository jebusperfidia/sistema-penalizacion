<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'semana_inicio',
        'semana_fin',
        'horas_faltantes',
        'monto_multa',
        'estado_pago',
        'fecha_pago',
    ];

    protected function casts(): array
    {
        return [
            'semana_inicio' => 'date',
            'semana_fin' => 'date',
            'horas_faltantes' => 'decimal:2',
            'monto_multa' => 'decimal:2',
            'estado_pago' => 'boolean',
            'fecha_pago' => 'datetime',
        ];
    }
}
