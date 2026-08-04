<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = ['titulo', 'category_id', 'fecha_inicio', 'estado'];

    // Un Goal tiene muchos registros de tiempo
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
