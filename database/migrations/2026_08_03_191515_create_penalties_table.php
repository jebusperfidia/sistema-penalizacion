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
            $table->date('semana_inicio');
            $table->date('semana_fin');
            // Decimal para soportar medias horas (ej. 2.5 horas)
            $table->decimal('horas_faltantes', 8, 2);
            $table->decimal('monto_multa', 10, 2);
            // El estado de pago por defecto siempre será falso al generarse la multa
            $table->boolean('estado_pago')->default(false);
            $table->timestamp('fecha_pago')->nullable();
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
