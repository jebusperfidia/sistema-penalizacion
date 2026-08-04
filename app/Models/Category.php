<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['nombre'];

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }
}
