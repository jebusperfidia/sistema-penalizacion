<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    protected $fillable = [
        'goal_id',
        'horas_invertidas',
        'fecha_registro',
        'notas_tecnicas',
        'es_reposicion'
    ];

    // Este registro pertenece a un Goal
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
