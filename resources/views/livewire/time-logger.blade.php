<?php

use App\Models\Goal;
use App\Models\TimeLog;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public $goal_id = '';
    public $horas_invertidas = '';
    public $fecha_registro;
    public $notas_tecnicas = '';

    public function mount()
    {
        // Por defecto la fecha es hoy, bloqueando por diseño cualquier intento de poner fechas pasadas por HTML manipulado
        $this->fecha_registro = Carbon::today()->toDateString();
    }

    public function saveLog()
    {
        $hoy = Carbon::today()->toDateString();

        // Regla de Inmutabilidad estricta: La fecha de registro debe ser estrictamente hoy
        $this->validate([
            'goal_id' => 'required|exists:goals,id',
            'horas_invertidas' => 'required|numeric|min:0.5|max:24',
            'fecha_registro' => "required|date|in:{$hoy}",
            'notas_tecnicas' => 'nullable|string|max:1000',
        ], [
            'fecha_registro.in' => 'Inmutabilidad activada: No puedes registrar horas de días anteriores. Lo que no se registró, se perdió.'
        ]);

        TimeLog::create([
            'goal_id' => $this->goal_id,
            'horas_invertidas' => $this->horas_invertidas,
            'fecha_registro' => $this->fecha_registro,
            'notas_tecnicas' => $this->notas_tecnicas,
        ]);

        $this->reset(['horas_invertidas', 'notas_tecnicas', 'goal_id']);
        $this->fecha_registro = $hoy;

        Flux::toast('Horas registradas correctamente.');
    }

    public function with(): array
    {
        return [
            'goals' => Goal::with('category')->orderBy('fecha_inicio', 'desc')->get(),
            'recentLogs' => TimeLog::with('goal.category')->orderBy('fecha_registro', 'desc')->take(10)->get()
        ];
    }
};
?>

<div>
    <div class="max-w-5xl mx-auto py-6">
        <flux:card>
            <flux:heading size="lg">Registrar Jornada de Estudio</flux:heading>
            <flux:subheading>Anota el avance técnico del día. Recuerda: la inmutabilidad aplica, solo se permite
                registrar el día en curso.</flux:subheading>

            <form wire:submit="saveLog" class="mt-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <flux:select wire:model="goal_id" label="Meta Asociada" placeholder="Selecciona la meta...">
                        @foreach($goals as $goal)
                        <flux:select.option value="{{ $goal->id }}">
                            {{ $goal->titulo }} ({{ $goal->category->nombre }})
                        </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input type="number" step="0.5" wire:model="horas_invertidas" label="Horas Invertidas"
                        placeholder="Ej: 2.5" />

                    <flux:input type="date" wire:model="fecha_registro" label="Fecha de Registro" readonly />

                    <div class="md:col-span-2">
                        <flux:textarea wire:model="notas_tecnicas" label="Notas Técnicas / Avances"
                            placeholder="¿Qué aprendiste o resolviste hoy?" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Guardar Registro</flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Historial Reciente -->
        <div class="mt-8">
            <flux:heading size="md" class="mb-4">Últimos Registros</flux:heading>

            <div class="space-y-3">
                @forelse($recentLogs as $log)
                <flux:card class="flex justify-between items-center py-3">
                    <div>
                        <div class="flex items-center space-x-3">
                            <flux:heading size="sm">{{ $log->goal->titulo ?? 'Meta eliminada' }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $log->horas_invertidas }} hrs</flux:badge>
                        </div>
                        <flux:subheading class="mt-1 text-xs">
                            {{ $log->fecha_registro }} | Notas: {{ $log->notas_tecnicas ?: 'Sin notas' }}
                        </flux:subheading>
                    </div>
                </flux:card>
                @empty
                <flux:card class="text-center text-gray-500">
                    Aún no hay registros de horas en este periodo.
                </flux:card>
                @endforelse
            </div>
        </div>
    </div>
</div>
