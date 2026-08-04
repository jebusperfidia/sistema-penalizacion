<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'semana_correspondiente',
        'horas_faltantes',
        'monto_penalizacion',
        'estado_pago'
    ];

    // Castear el booleano para que Laravel lo trate como true/false y no como 1/0
    protected $casts = [
        'estado_pago' => 'boolean',
    ];
}
