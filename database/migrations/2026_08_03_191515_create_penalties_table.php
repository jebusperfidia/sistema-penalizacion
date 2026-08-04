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
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->string('semana_correspondiente'); // Guardaremos el formato de semana, ej. "2026-W32"
            $table->decimal('horas_faltantes', 5, 2);
            $table->decimal('monto_penalizacion', 8, 2); // $100 MXN por hora faltante
            $table->boolean('estado_pago')->default(false); // Por defecto, debes la lana
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
