<?php

use App\Models\TimeLog;
use App\Models\Goal;
use App\Models\Penalty;
use App\Models\ExtraHourMovement;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Flux\Flux;

new class extends Component {
    public $horasAplicar = '';
    public $motivoAplicacion = '';

    #[\Livewire\Attributes\On('log-saved')]
    #[\Livewire\Attributes\On('extra-hours-updated')]
    public function refreshStats()
    {
        // Se ejecuta para refrescar las estadísticas
    }

    public function aplicarHorasExtra()
    {
        $saldoDisponible = ExtraHourMovement::saldoDisponible();

        $this->validate([
            'horasAplicar' => [
                'required',
                'numeric',
                'min:0.5',
                'max:' . max(0.5, $saldoDisponible),
            ],
            'motivoAplicacion' => 'nullable|string|max:255',
        ], [
            'horasAplicar.required' => 'Indica cuántas horas deseas aplicar.',
            'horasAplicar.numeric'  => 'El valor debe ser un número válido.',
            'horasAplicar.min'      => 'El mínimo a aplicar es 0.5 horas.',
            'horasAplicar.max'      => 'No puedes aplicar más horas de las disponibles en tu saldo (' . number_format($saldoDisponible, 1) . ' hrs).',
        ]);

        if ($saldoDisponible < (float) $this->horasAplicar) {
            \Masmerise\Toaster\Toaster::error('Saldo insuficiente de horas extras.');
            return;
        }

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        ExtraHourMovement::create([
            'tipo' => 'aplicacion',
            'horas' => (float) $this->horasAplicar,
            'semana_inicio' => $startOfWeek->toDateString(),
            'semana_fin' => $endOfWeek->toDateString(),
            'descripcion' => $this->motivoAplicacion ?: ('Compensación de ' . number_format((float) $this->horasAplicar, 1) . 'h a la semana del ' . $startOfWeek->format('d/m') . ' al ' . $endOfWeek->format('d/m/Y')),
        ]);

        $horasAplicadasTexto = number_format((float) $this->horasAplicar, 1);
        $this->reset(['horasAplicar', 'motivoAplicacion']);

        Flux::modal('apply-extra-hours')->close();
        $this->dispatch('extra-hours-updated');
        \Masmerise\Toaster\Toaster::success("¡Se aplicaron {$horasAplicadasTexto} hrs extra a esta semana con éxito!");
    }

    public function with(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // 1. Horas registradas en la semana actual mediante bitácora
        $horasRegistradasSemana = (float) TimeLog::whereBetween('fecha_registro', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->sum('horas_invertidas');

        // 2. Horas extra aplicadas voluntariamente a la semana actual
        $horasExtraAplicadasEstaSemana = ExtraHourMovement::horasAplicadasEnSemana($startOfWeek);

        // 3. Horas totales efectivas (registradas + extras aplicadas)
        $horasEfectivasSemana = $horasRegistradasSemana + $horasExtraAplicadasEstaSemana;

        // 4. Saldo total disponible en la bolsa de horas extra
        $saldoHorasExtra = ExtraHourMovement::saldoDisponible();

        // 5. Historial reciente de movimientos de horas extras
        $movimientosHorasExtra = ExtraHourMovement::orderBy('created_at', 'desc')->take(6)->get();

        $totalHorasHistoricas = (float) TimeLog::sum('horas_invertidas');
        $metasActivas = Goal::where('estado', false)->count();
        $multasPendientes = Penalty::where('estado_pago', false)->count();
        $metaSemanal = 16.0;

        return [
            'horasRegistradasSemana'        => $horasRegistradasSemana,
            'horasExtraAplicadasEstaSemana' => $horasExtraAplicadasEstaSemana,
            'horasEfectivasSemana'          => $horasEfectivasSemana,
            'saldoHorasExtra'               => $saldoHorasExtra,
            'movimientosHorasExtra'         => $movimientosHorasExtra,
            'totalHorasHistoricas'          => $totalHorasHistoricas,
            'metasActivas'                  => $metasActivas,
            'multasPendientes'              => $multasPendientes,
            'metaSemanal'                   => $metaSemanal,
            'inicioSemana'                  => $startOfWeek,
            'finSemana'                     => $endOfWeek,
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
        <div class="flex flex-wrap items-center gap-3">
            <flux:modal.trigger name="apply-extra-hours">
                <flux:button variant="subtle" icon="sparkles"
                    class="transition-transform duration-150 hover:scale-105 hover:shadow-sm cursor-pointer border-amber-300 dark:border-amber-700/60 text-amber-600 dark:text-amber-400">
                    Bolsa de Horas ({{ number_format($saldoHorasExtra, 1) }}h)
                </flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="time-log-history">
                <flux:button variant="subtle" icon="clock"
                    class="transition-transform duration-150 hover:scale-105 hover:shadow-sm cursor-pointer">
                    Historial
                </flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="create-time-log">
                <flux:button variant="primary" icon="plus"
                    class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer">
                    Registrar Horas
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:time-logger />

    <!-- Cards Stats Grid (4 cards clásicas en 1 solo bloque) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- 1. Horas Semanales Invertidas -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Horas esta Semana</flux:subheading>
                <flux:icon name="clock" class="w-5 h-5 text-emerald-500" />
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($horasEfectivasSemana, 1) }}</span>
                <span class="text-xs text-zinc-500">/ {{ number_format($metaSemanal, 0) }} hrs meta</span>
            </div>
            
            <!-- Barra de Progreso hacia la Meta -->
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mt-2">
                <div class="bg-emerald-600 h-2 rounded-full transition-all duration-300"
                    style="width: {{ min(100, ($horasEfectivasSemana / $metaSemanal) * 100) }}%"></div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-zinc-500 pt-1">
                <span>{{ number_format($horasRegistradasSemana, 1) }}h en bitácora</span>
                @if($horasExtraAplicadasEstaSemana > 0)
                <span class="text-amber-600 dark:text-amber-400 font-semibold">+{{ number_format($horasExtraAplicadasEstaSemana, 1) }}h extra</span>
                @endif
            </div>
        </flux:card>

        <!-- 2. Total de Horas Registradas -->
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

        <!-- 3. Metas Activas -->
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

        <!-- 4. Estado de Cuenta / Bolsa -->
        <flux:card class="space-y-2 transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <flux:subheading>Estado de Cuenta</flux:subheading>
                <flux:icon name="shield-check"
                    class="w-5 h-5 {{ $multasPendientes > 0 ? 'text-red-500' : 'text-emerald-500' }}" />
            </div>
            <div class="flex items-baseline justify-between">
                @if($multasPendientes > 0)
                <span class="text-xl font-bold text-red-600 dark:text-red-400">Multa Pendiente</span>
                @else
                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Al día</span>
                @endif

                @if($saldoHorasExtra > 0)
                <flux:modal.trigger name="apply-extra-hours">
                    <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-800 cursor-pointer hover:bg-amber-100">
                        {{ number_format($saldoHorasExtra, 1) }}h extra
                    </span>
                </flux:modal.trigger>
                @endif
            </div>
            <p class="text-xs text-zinc-500 mt-2">
                {{ $multasPendientes > 0 ? 'Tienes un cobro sin liquidar' : ($saldoHorasExtra > 0 ? 'Tienes horas a favor en tu bolsa' : 'Sin penalizaciones activas') }}
            </p>
        </flux:card>
    </div>

    <!-- Modal: Aplicar Horas Extra a la Semana -->
    <flux:modal name="apply-extra-hours" class="md:w-[560px] space-y-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400">
                    <flux:icon name="sparkles" class="w-5 h-5" />
                </div>
                <flux:heading size="lg">Bolsa de Horas Extras</flux:heading>
            </div>
            <flux:subheading>Aplica tus horas acumuladas a favor para reducir o evitar penalizaciones en la semana en curso.</flux:subheading>
        </div>

        <!-- Resumen de Estado -->
        <div class="bg-zinc-50 dark:bg-zinc-800/60 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block">Saldo Disponible</span>
                    <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($saldoHorasExtra, 1) }} hrs</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block">Semana en Curso</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ $inicioSemana->translatedFormat('d M') }} – {{ $finSemana->translatedFormat('d M') }}
                    </span>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/70 text-xs text-zinc-600 dark:text-zinc-400 space-y-1.5">
                <div class="flex justify-between">
                    <span>Horas registradas en bitácora:</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ number_format($horasRegistradasSemana, 1) }} h</span>
                </div>
                <div class="flex justify-between">
                    <span>Horas extra ya aplicadas:</span>
                    <span class="font-semibold text-amber-600 dark:text-amber-400">+{{ number_format($horasExtraAplicadasEstaSemana, 1) }} h</span>
                </div>
                <div class="flex justify-between font-medium pt-1.5 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                    <span>Total acumulado vs Meta ({{ number_format($metaSemanal, 0) }}h):</span>
                    <span class="{{ $horasEfectivasSemana >= $metaSemanal ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }} font-bold">
                        {{ number_format($horasEfectivasSemana, 1) }} / {{ number_format($metaSemanal, 0) }} h
                        @if($horasEfectivasSemana < $metaSemanal)
                            <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Faltan {{ number_format($metaSemanal - $horasEfectivasSemana, 1) }}h)</span>
                        @else
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">(¡Meta cubierta!)</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        @if($saldoHorasExtra > 0)
        <form wire:submit="aplicarHorasExtra" class="space-y-4">
            <div class="space-y-1">
                <flux:input type="number" step="0.5" min="0.5" max="{{ $saldoHorasExtra }}"
                    wire:model="horasAplicar"
                    label="Horas a aplicar a esta semana"
                    placeholder="Ej. 1.0" />
                <p class="text-xs text-zinc-500">Puedes aplicar desde 0.5 hasta {{ number_format($saldoHorasExtra, 1) }} horas de tu saldo.</p>
            </div>

            <div>
                <flux:input type="text" wire:model="motivoAplicacion"
                    label="Nota o Motivo (opcional)"
                    placeholder="Ej. Compensación por imprevisto laboral" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="subtle" class="cursor-pointer">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled"
                    class="bg-amber-600 hover:bg-amber-700 text-white cursor-pointer flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading wire:target="aplicarHorasExtra" />
                    <span>Aplicar Horas</span>
                </flux:button>
            </div>
        </form>
        @else
        <div class="p-4 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-center text-sm text-zinc-500 dark:text-zinc-400">
            No tienes saldo disponible de horas extras actualmente. Las horas que superen las 16 horas en una semana se acumularán automáticamente en tu bolsa.
        </div>
        @endif

        <!-- Historial de Movimientos de la Bolsa -->
        <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/70">
            <flux:heading size="sm" class="mb-2">Últimos Movimientos de la Bolsa</flux:heading>
            <div class="max-h-48 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                @forelse($movimientosHorasExtra as $mov)
                <div class="flex items-center justify-between p-2.5 rounded-lg border border-zinc-200/70 dark:border-zinc-700/50 text-xs bg-white dark:bg-zinc-800/40">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($mov->tipo === 'acumulacion')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0">
                            + Abono
                        </span>
                        @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">
                            - Uso
                        </span>
                        @endif
                        <span class="text-zinc-600 dark:text-zinc-300 truncate">{{ $mov->descripcion }}</span>
                    </div>
                    <div class="font-bold {{ $mov->tipo === 'acumulacion' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} shrink-0 ml-2">
                        {{ $mov->tipo === 'acumulacion' ? '+' : '-' }}{{ number_format($mov->horas, 1) }} h
                    </div>
                </div>
                @empty
                <p class="text-xs text-zinc-400 text-center py-2">Sin movimientos registrados aún.</p>
                @endforelse
            </div>
        </div>
    </flux:modal>

    <!-- Tabla de Metas (PowerGrid) -->
    <flux:card class="w-full p-2 sm:p-4 transition-all duration-200 hover:shadow-lg">
        <div class="mb-4 px-2">
            <flux:heading size="lg">Metas de Aprendizaje</flux:heading>
            <flux:subheading>Detalle de materias y progreso de tiempo asignado.</flux:subheading>
        </div>

        <div class="w-full">
            <livewire:goal-table />
        </div>
    </flux:card>
</div>
