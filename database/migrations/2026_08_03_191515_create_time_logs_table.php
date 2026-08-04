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
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            // Llave foránea. Si borras un goal, se borran sus horas.
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');
            $table->decimal('horas_invertidas', 5, 2); // Decimal por si registras 1.5 horas
            $table->date('fecha_registro');
            $table->text('notas_tecnicas')->nullable(); // Notas opcionales de lo que estudiaste
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
