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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            // Vínculo directo a la tabla de materias
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->integer('horas_objetivo');
            $table->date('fecha_inicio');
            $table->date('fecha_limite');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
