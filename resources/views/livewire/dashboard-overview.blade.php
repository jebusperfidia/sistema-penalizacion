<?php

use App\Models\TimeLog;
use App\Models\Goal;
use App\Models\Penalty;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    #[\Livewire\Attributes\On('log-saved')]
    public function refreshStats()
    {
        // Se ejecuta al guardar registro desde el modal
    }

    public function with(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $horasSemanales = TimeLog::whereBetween('fecha_registro', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->sum('horas_invertidas');

        $totalHorasHistoricas = TimeLog::sum('horas_invertidas');
        $metasActivas = Goal::where('estado', false)->count();
        $multasPendientes = Penalty::where('estado_pago', false)->count();

        return [
            'horasSemanales' => $horasSemanales,
            'totalHorasHistoricas' => $totalHorasHistoricas,
            'metasActivas' => $metasActivas,
            'multasPendientes' => $multasPendientes,
            'metaSemanal' => 16,
        ];
    }
};
?>

<div class="w-full space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Panel Principal</flux:heading>
            <flux:subheading>Resumen de rendimiento y seguimiento de metas de estudio.</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            <flux:modal.trigger name="time-log-history">
                <flux:button variant="subtle" icon="clock" class="transition-transform duration-150 hover:scale-105 hover:shadow-sm cursor-pointer">
                    Historial
                </flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="create-time-log">
                <flux:button variant="primary" icon="plus" class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer">
                    Registrar Horas
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:time-logger />

    <!-- Cards Stats Grid (Aprovechando todo el ancho) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- Horas Semanales Invertidas -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Horas esta Semana</flux:subheading>
                <flux:icon name="clock" class="w-5 h-5 text-emerald-500" />
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($horasSemanales, 1) }}</span>
                <span class="text-xs text-zinc-500">/ {{ $metaSemanal }} hrs meta</span>
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mt-2">
                <div class="bg-emerald-600 h-2 rounded-full transition-all duration-300" style="width: {{ min(100, ($horasSemanales / $metaSemanal) * 100) }}%"></div>
            </div>
        </flux:card>

        <!-- Total de Horas Registradas -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Total Horas Registradas</flux:subheading>
                <flux:icon name="chart-bar" class="w-5 h-5 text-emerald-500" />
            </div>
            <div>
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalHorasHistoricas, 1) }}</span>
                <span class="text-xs text-zinc-500 ml-1">hrs acumuladas</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Histórico global de aprendizaje</p>
        </flux:card>

        <!-- Metas Activas -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Metas Activas</flux:subheading>
                <flux:icon name="book-open" class="w-5 h-5 text-blue-500" />
            </div>
            <div>
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $metasActivas }}</span>
                <span class="text-xs text-zinc-500 ml-1">en progreso</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Bloques de estudio activos</p>
        </flux:card>

        <!-- Multas / Estado -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Estado de Cuenta</flux:subheading>
                <flux:icon name="shield-check" class="w-5 h-5 {{ $multasPendientes > 0 ? 'text-red-500' : 'text-emerald-500' }}" />
            </div>
            <div>
                @if($multasPendientes > 0)
                    <span class="text-xl font-bold text-red-600 dark:text-red-400">Multa Pendiente</span>
                @else
                    <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Al día</span>
                @endif
            </div>
            <p class="text-xs text-zinc-500 mt-2">
                {{ $multasPendientes > 0 ? 'Tienes un cobro sin liquidar' : 'Sin penalizaciones activas' }}
            </p>
        </flux:card>
    </div>

    <!-- Tabla de Metas (PowerGrid Mejorado ocupando todo el ancho) -->
    <flux:card class="w-full p-4 sm:p-6 transition-all duration-200 hover:shadow-lg">
        <div class="mb-4">
            <flux:heading size="lg">Metas de Aprendizaje</flux:heading>
            <flux:subheading>Detalle de materias y progreso de tiempo asignado.</flux:subheading>
        </div>

        <div class="w-full overflow-x-auto">
            <livewire:goal-table />
        </div>
    </flux:card>
</div>
