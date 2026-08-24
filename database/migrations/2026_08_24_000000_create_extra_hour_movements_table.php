<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extra_hour_movements', function (Blueprint $table) {
            $table->id();
            // 'acumulacion' (superávit de semana con >16h), 'aplicacion' (uso de horas para compensar una semana), 'ajuste'
            $table->string('tipo', 30);
            $table->decimal('horas', 8, 2);
            $table->date('semana_inicio');
            $table->date('semana_fin');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_hour_movements');
    }
};
