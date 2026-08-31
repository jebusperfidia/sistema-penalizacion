<?php

use App\Models\Goal;
use App\Models\TimeLog;
use App\Models\Penalty;
use App\Models\ExtraHourMovement;
use App\Services\WeeklyGoalEvaluator;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Flux\Flux;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $goal_id = '';
    public $horas_invertidas = '';
    public $fecha_registro;
    public $notas_tecnicas = '';

    public int $semanaOffset = 0;

    #[\Livewire\Attributes\On('penalty-settled')]
    public function refreshState(): void {}

    public function mount()
    {
        $this->fecha_registro = Carbon::today()->toDateString();
    }

    public function semanaAnterior(): void
    {
        $this->semanaOffset--;
    }

    public function semanaSiguiente(): void
    {
        if ($this->semanaOffset < 0) {
            $this->semanaOffset++;
        }
    }

    public function liquidarMultaYContinuar(): void
    {
        $penalizacion = WeeklyGoalEvaluator::getPenalizacionActiva();
        if ($penalizacion) {
            $penalizacion->update([
                'estado_pago' => true,
                'fecha_pago'  => Carbon::now(),
            ]);

            $this->dispatch('penalty-settled');
            $this->dispatch('extra-hours-updated');
            Toaster::success('¡Multa liquidada! Tu registro ha sido desbloqueado.');
        }
    }

    public function saveLog()
    {
        if (WeeklyGoalEvaluator::getPenalizacionActiva()) {
            Toaster::error('Bloqueo activo: Debes confirmar la transferencia de la penalización antes de registrar horas.');
            return;
        }

        $hoy = Carbon::today();
        $hoyStr = $hoy->toDateString();

        $this->validate([
            'goal_id'          => 'required|exists:goals,id',
            'horas_invertidas' => 'required|numeric|min:0.5|max:24',
            'fecha_registro'   => "required|date|in:{$hoyStr}",
            'notas_tecnicas'   => 'nullable|string|max:1000',
        ], [
            'fecha_registro.in' => 'Inmutabilidad activada: No puedes registrar horas de días anteriores. Lo que no se registró, se perdió.'
        ]);

        $horasCapturadas = (float) $this->horas_invertidas;

        // ===== LÓGICA INTELIGENTE DE REPOSICIÓN LOS LUNES =====
        if ($hoy->isMonday()) {
            $inicioPasada = $hoy->copy()->subWeek()->startOfWeek();
            $finPasada = $hoy->copy()->subWeek()->endOfWeek();

            $horasPasada = (float) TimeLog::whereBetween('fecha_registro', [$inicioPasada->toDateString(), $finPasada->toDateString()])
                ->where('es_reposicion', false)->sum('horas_invertidas');

            $horasExtraPasada = (float) ExtraHourMovement::horasAplicadasEnSemana($inicioPasada);
            $totalPasada = $horasPasada + $horasExtraPasada;
            $meta = 16.0;

            if ($totalPasada < $meta) {
                $deficit = round($meta - $totalPasada, 2);
                $abonadoHoy = (float) TimeLog::where('fecha_registro', $hoyStr)->where('es_reposicion', true)->sum('horas_invertidas');
                $deficitPendiente = round($deficit - $abonadoHoy, 2);

                if ($deficitPendiente > 0) {
                    if ($horasCapturadas <= $deficitPendiente) {
                        // El registro completo se va para pagar la deuda
                        TimeLog::create([
                            'goal_id' => $this->goal_id, 'horas_invertidas' => $horasCapturadas,
                            'fecha_registro' => $hoyStr, 'notas_tecnicas' => $this->notas_tecnicas,
                            'es_reposicion' => true,
                        ]);
                        Toaster::success("¡Deuda abonada! {$horasCapturadas}h se fueron al déficit de la semana pasada.");
                    } else {
                        // Se divide el registro: Una parte paga, la otra sobra para la nueva semana
                        TimeLog::create([
                            'goal_id' => $this->goal_id, 'horas_invertidas' => $deficitPendiente,
                            'fecha_registro' => $hoyStr, 'notas_tecnicas' => trim($this->notas_tecnicas . ' [Abono Deuda]'),
                            'es_reposicion' => true,
                        ]);

                        $sobrante = round($horasCapturadas - $deficitPendiente, 2);
                        TimeLog::create([
                            'goal_id' => $this->goal_id, 'horas_invertidas' => $sobrante,
                            'fecha_registro' => $hoyStr, 'notas_tecnicas' => trim($this->notas_tecnicas . ' [Sobrante Nueva Sem.]'),
                            'es_reposicion' => false,
                        ]);
                        Toaster::success("¡Deuda saldada! ({$deficitPendiente}h). El resto ({$sobrante}h) contó para la nueva semana.");
                    }

                    $this->finalizarGuardado($hoyStr);
                    return;
                }
            }
        }

        // ===== GUARDADO NORMAL (Si no es lunes o no hay deuda) =====
        TimeLog::create([
            'goal_id' => $this->goal_id, 'horas_invertidas' => $horasCapturadas,
            'fecha_registro' => $hoyStr, 'notas_tecnicas' => $this->notas_tecnicas,
            'es_reposicion' => false,
        ]);
        Toaster::success('Horas registradas correctamente.');
        $this->finalizarGuardado($hoyStr);
    }

    private function finalizarGuardado($hoyStr)
    {
        $this->reset(['horas_invertidas', 'notas_tecnicas', 'goal_id']);
        $this->fecha_registro = $hoyStr;
        $this->dispatch('log-saved');
        Flux::modal('create-time-log')->close();
    }

    public function with(): array
    {
        WeeklyGoalEvaluator::evaluarSemanasPendientes();
        $penalizacionActiva = WeeklyGoalEvaluator::getPenalizacionActiva();

        $inicioSemana = Carbon::now()->startOfWeek()->addWeeks($this->semanaOffset);
        $finSemana    = $inicioSemana->copy()->endOfWeek();

        $logsDeSemanaBrutos = TimeLog::with('goal.category')
            ->whereBetween('fecha_registro', [$inicioSemana->toDateString(), $finSemana->toDateString()])
            ->orderBy('fecha_registro', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalHorasSemana  = $logsDeSemanaBrutos->sum('horas_invertidas');
        $diasConRegistro   = $logsDeSemanaBrutos->groupBy('fecha_registro')->count();
        $logsAgrupados     = $logsDeSemanaBrutos->groupBy('fecha_registro');

        return [
            'goals'              => Goal::with('category')->orderBy('fecha_inicio', 'desc')->get(),
            'logsAgrupados'      => $logsAgrupados,
            'totalHorasSemana'   => $totalHorasSemana,
            'diasConRegistro'    => $diasConRegistro,
            'inicioSemana'       => $inicioSemana,
            'finSemana'          => $finSemana,
            'esSemanaActual'     => $this->semanaOffset === 0,
            'penalizacionActiva' => $penalizacionActiva,
        ];
    }
};
?>

<div>
    {{-- ===== MODAL: REGISTRAR HORAS / AVISO DE BLOQUEO ===== --}}
    <flux:modal name="create-time-log" class="md:w-[560px] space-y-6">
        @if($penalizacionActiva)
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="p-2.5 rounded-xl bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 shrink-0">
                    <flux:icon name="lock-closed" class="w-5 h-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-white">Registro Bloqueado</flux:heading>
                    <flux:subheading>Tienes una penalización pendiente por incumplimiento de meta.</flux:subheading>
                </div>
            </div>
        </div>

        <!-- Resumen de Penalización -->
        <div
            class="bg-zinc-50 dark:bg-zinc-800/60 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block">Semana Evaluada</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ \Carbon\Carbon::parse($penalizacionActiva->semana_inicio)->translatedFormat('d M') }} – {{
                        \Carbon\Carbon::parse($penalizacionActiva->semana_fin)->translatedFormat('d M, Y') }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block">Horas Faltantes</span>
                    <span class="text-base font-bold text-red-600 dark:text-red-400">
                        {{ number_format($penalizacionActiva->horas_faltantes, 1) }} hrs
                    </span>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/70 flex items-center justify-between">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 block">Monto a Transferir (Ahorro)</span>
                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">($100.00 MXN por hora faltante)</span>
                </div>
                <span class="text-2xl font-bold text-red-600 dark:text-red-400">
                    ${{ number_format($penalizacionActiva->monto_multa, 2) }} MXN
                </span>
            </div>
        </div>

        <div
            class="flex items-start gap-2.5 p-3 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-xs text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
            <flux:icon name="information-circle" class="w-4 h-4 text-zinc-500 shrink-0 mt-0.5" />
            <span>Para mantener tu disciplina financiera, realiza la transferencia a tu cuenta de ahorro y confirma a
                continuación para desbloquear el registro.</span>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:modal.close>
                <flux:button variant="subtle" class="cursor-pointer">
                    Cancelar
                </flux:button>
            </flux:modal.close>
            <flux:button wire:click="liquidarMultaYContinuar" variant="danger" wire:loading.attr="disabled"
                class="cursor-pointer flex items-center gap-2">
                <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading
                    wire:target="liquidarMultaYContinuar" />
                <span>Confirmar Transferencia y Desbloquear</span>
            </flux:button>
        </div>
        @else
        <div>
            <flux:heading size="lg">Registrar Jornada de Estudio</flux:heading>
            <flux:subheading>Anota el avance técnico del día. Recuerda: la fecha es inmutable y corresponde al día en
                curso.</flux:subheading>
        </div>

        <form wire:submit="saveLog" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <flux:select wire:model="goal_id" label="Meta Asociada" placeholder="Selecciona la meta...">
                    @foreach($goals as $goal)
                    <flux:select.option value="{{ $goal->id }}">
                        {{ $goal->titulo }} ({{ $goal->category->nombre }})
                    </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" step="0.5" wire:model="horas_invertidas" label="Horas Invertidas"
                    placeholder="Ej: 2.5" />

                <flux:input type="date" wire:model="fecha_registro" label="Fecha de Registro" readonly
                    class="md:col-span-2" />

                <div class="md:col-span-2">
                    <flux:textarea wire:model="notas_tecnicas" label="Notas Técnicas / Avances"
                        placeholder="¿Qué aprendiste o resolviste hoy?" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="subtle"
                        class="transition-all duration-150 hover:bg-zinc-200 dark:hover:bg-zinc-700 cursor-pointer">
                        Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled"
                    class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading wire:target="saveLog" />
                    <span>Guardar Registro</span>
                </flux:button>
            </div>
        </form>
        @endif
    </flux:modal>

    {{-- ===== MODAL: HISTORIAL DE HORAS (Ancho forzado a 4xl) ===== --}}
    <flux:modal name="time-log-history" class="w-full !max-w-4xl">
        <div class="flex flex-col gap-6">

            {{-- ── Header ── --}}
            <div>
                <flux:heading size="lg">Bitácora de Estudio</flux:heading>
                <flux:subheading>Historial cronológico de jornadas registradas.</flux:subheading>
            </div>

            {{-- ── Navegador de semana (pill centrado) ── --}}
            <div class="flex items-center justify-center gap-3">
                <button wire:click="semanaAnterior"
                    class="flex items-center justify-center w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all duration-150 cursor-pointer shrink-0"
                    title="Semana anterior">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div
                    class="flex items-center gap-2 px-5 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-full border border-zinc-200 dark:border-zinc-700/50 min-w-[220px] justify-center">
                    @if($esSemanaActual)
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Semana actual</span>
                    @else
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $inicioSemana->translatedFormat('d M') }} – {{ $finSemana->translatedFormat('d M, Y') }}
                    </span>
                    @endif
                </div>

                <button wire:click="semanaSiguiente" @disabled($esSemanaActual)
                    class="flex items-center justify-center w-8 h-8 rounded-full border transition-all duration-150 shrink-0
                    {{ $esSemanaActual
                        ? 'border-zinc-100 dark:border-zinc-800/50 text-zinc-300 dark:text-zinc-600 cursor-not-allowed'
                        : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer' }}"
                    title="Semana siguiente">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            {{-- ── Stats de la semana ── --}}
            <div class="grid grid-cols-2 gap-4">
                <div
                    class="flex items-center gap-4 px-5 py-4 rounded-xl border border-emerald-200/60 dark:border-emerald-500/10 bg-emerald-50/50 dark:bg-emerald-500/5">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 leading-none">
                            {{ number_format($totalHorasSemana, 1) }}<span class="text-sm font-medium ml-1">h</span>
                        </p>
                        <p class="text-xs text-emerald-600/80 dark:text-emerald-400/70 mt-1">Total registrado</p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-4 px-5 py-4 rounded-xl border border-zinc-200 dark:border-zinc-700/50 bg-zinc-50 dark:bg-zinc-800/30">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-full bg-zinc-200/50 dark:bg-zinc-700/50 text-zinc-500 dark:text-zinc-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-700 dark:text-zinc-200 leading-none">
                            {{ $diasConRegistro }}<span class="text-sm font-medium ml-1">días</span>
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Con actividad</p>
                    </div>
                </div>
            </div>

            {{-- ── Feed cronológico (Estilo Agenda con Altura Fija) ── --}}
            <div class="h-[450px] overflow-y-auto overflow-x-hidden pr-2 custom-scrollbar">

                <div class="space-y-6 pb-4">
                    @forelse($logsAgrupados as $fecha => $logsDelDia)
                    <div class="w-full">

                        {{-- Cabecera del día (Pegajosa, con padding horizontal px-4 para respirar) --}}
                        <div
                            class="sticky top-0 z-10 flex items-center gap-4 bg-white dark:bg-zinc-900 py-3 px-4 mb-3 border-b border-zinc-100 dark:border-zinc-800/60">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 shrink-0">
                                <span class="text-lg font-bold text-zinc-700 dark:text-zinc-200">{{
                                    \Carbon\Carbon::parse($fecha)->format('d') }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-100 capitalize">{{
                                    \Carbon\Carbon::parse($fecha)->translatedFormat('l') }}</p>
                                <p class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($fecha)->translatedFormat('F
                                    Y') }}</p>
                            </div>
                            <div
                                class="shrink-0 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-bold text-sm rounded-full shadow-sm">
                                {{ number_format($logsDelDia->sum('horas_invertidas'), 1) }} h
                            </div>
                        </div>

                        {{-- Tarjetas de actividades --}}
                        <div class="space-y-3 w-full px-1">
                            @foreach($logsDelDia as $log)
                            <div x-data="{ open: false }"
                                class="group w-full flex flex-col p-4 rounded-xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/40 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <div class="flex items-start gap-4 w-full">
                                    {{-- Info Principal (Sin truncate para que el título baje a 2 líneas si lo necesita)
                                    --}}
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200 leading-snug">
                                            {{ $log->goal->titulo ?? '—' }}
                                        </p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1.5">
                                            {{ $log->goal->category->nombre ?? '—' }}
                                        </p>
                                    </div>

                                    {{-- Badge y Botón --}}
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ number_format($log->horas_invertidas, 1) }} h
                                        </span>

                                        @if($log->notas_tecnicas)
                                        <button @click="open = !open"
                                            class="flex items-center justify-center w-8 h-8 rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200 dark:hover:text-zinc-200 dark:hover:bg-zinc-700 transition-all cursor-pointer"
                                            :class="open ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' : ''">
                                            <svg class="w-4 h-4 transition-transform duration-200"
                                                :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        @else
                                        <div class="w-8"></div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Notas Expandibles (Alineadas a la izquierda y sin espacios raros) --}}
                                @if($log->notas_tecnicas)
                                <div x-show="open" x-collapse
                                    class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-700/50 text-left"
                                    style="display: none;">
                                    <p
                                        class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed whitespace-pre-line">
                                        {{ trim($log->notas_tecnicas) }}</p>
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div
                            class="flex items-center justify-center w-16 h-16 rounded-full bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/50 mb-4 mt-16">
                            <svg class="w-8 h-8 text-zinc-300 dark:text-zinc-500" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-base font-medium text-zinc-600 dark:text-zinc-300">Sin registros esta semana</p>
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">Navega a otra semana para ver tu
                            historial.</p>
                    </div>
                    @endforelse
                </div>
            </div>

        </div>
    </flux:modal>
</div>

{{-- Estilos para el scrollbar de la bitácora --}}
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #3f3f46;
        border-radius: 10px;
    }

    :is(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #52525b;
    }
</style>