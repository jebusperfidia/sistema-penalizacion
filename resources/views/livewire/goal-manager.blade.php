<?php

use App\Models\Goal;
use App\Models\Category;
use Livewire\Volt\Component;
use Flux\Flux;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $titulo = '';
    public $category_id = '';
    public $fecha_inicio = '';
    public $editandoId = null;

    public function openCreateModal()
    {
        $this->reset(['titulo', 'category_id', 'fecha_inicio', 'editandoId']);
        $this->resetValidation();
        Flux::modal('goal-modal')->show();
    }

    public function saveGoal()
    {
        $this->validate([
            'titulo' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'fecha_inicio' => 'required|date',
        ]);

        if ($this->editandoId) {
            Goal::find($this->editandoId)->update([
                'titulo' => $this->titulo,
                'category_id' => $this->category_id,
                'fecha_inicio' => $this->fecha_inicio,
            ]);
            Toaster::success('Meta actualizada con éxito.');
        } else {
            Goal::create([
                'titulo' => $this->titulo,
                'category_id' => $this->category_id,
                'fecha_inicio' => $this->fecha_inicio,
            ]);
            Toaster::success('Meta registrada con éxito.');
        }

        $this->reset(['titulo', 'category_id', 'fecha_inicio', 'editandoId']);
        Flux::modal('goal-modal')->close();
    }

    public function editGoal($id)
    {
        $goal = Goal::find($id);
        $this->titulo = $goal->titulo;
        $this->category_id = $goal->category_id;
        $this->fecha_inicio = $goal->fecha_inicio;
        $this->editandoId = $goal->id;
        $this->resetValidation();
        Flux::modal('goal-modal')->show();
    }

    public function deleteGoal($id)
    {
        Goal::find($id)->delete();
        Toaster::success('Meta eliminada correctamente.');
    }

    public function with(): array
    {
        return [
            'categorias' => Category::orderBy('nombre')->get(),
            'goals' => Goal::with('category')->orderBy('fecha_inicio', 'desc')->get(),
        ];
    }
};
?>

<div>
    <div class="max-w-4xl mx-auto py-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Gestión de Metas</flux:heading>
                <flux:subheading>Asigna y gestiona bloques de estudio o proyectos de aprendizaje.</flux:subheading>
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer">
                Nueva Meta
            </flux:button>
        </div>

        <!-- Listado de Metas -->
        <div class="space-y-3">
            @forelse($goals as $goal)
            <flux:card class="flex justify-between items-center py-3.5 px-4 transition-all duration-200 hover:shadow-md" wire:key="goal-{{ $goal->id }}">
                <div class="space-y-1.5 max-w-[80%]">
                    <flux:heading size="sm" class="font-medium text-zinc-900 dark:text-white leading-snug">{{ $goal->titulo }}</flux:heading>
                    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $goal->category->nombre ?? 'Sin categoría' }}</span>
                        <span>•</span>
                        <span>Fecha inicio: {{ \Carbon\Carbon::parse($goal->fecha_inicio)->format('d/m/Y') }}</span>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <flux:button type="button" wire:click="editGoal({{ $goal->id }})" wire:loading.attr="disabled" wire:target="editGoal({{ $goal->id }})" size="sm" class="transition-transform duration-150 hover:scale-105 cursor-pointer flex items-center justify-center !text-white !bg-[#2299dd] hover:!bg-[#1b7eb8] border-none">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-white" wire:loading wire:target="editGoal({{ $goal->id }})" />
                        <svg wire:loading.remove wire:target="editGoal({{ $goal->id }})" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </flux:button>

                    <flux:button type="button" wire:click="deleteGoal({{ $goal->id }})" wire:loading.attr="disabled" wire:target="deleteGoal({{ $goal->id }})" size="sm" variant="danger" class="transition-transform duration-150 hover:scale-105 cursor-pointer flex items-center justify-center !text-white">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-white" wire:loading wire:target="deleteGoal({{ $goal->id }})" />
                        <svg wire:loading.remove wire:target="deleteGoal({{ $goal->id }})" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </flux:button>
                </div>
            </flux:card>
            @empty
            <flux:card class="text-center text-gray-500 py-8">
                Aún no has registrado metas. Haz clic en "Nueva Meta" para comenzar.
            </flux:card>
            @endforelse
        </div>
    </div>

    <!-- Modal Único de Meta (Crear / Editar) -->
    <flux:modal name="goal-modal" class="md:w-[600px] space-y-6">
        <div>
            <flux:heading size="lg">{{ $editandoId ? 'Editar Meta' : 'Nueva Meta de Aprendizaje' }}</flux:heading>
            <flux:subheading>{{ $editandoId ? 'Actualiza los parámetros de la meta seleccionada.' : 'Asigna un nuevo bloque de estudio con su materia respectiva.' }}</flux:subheading>
        </div>

        <form wire:submit="saveGoal" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <flux:input wire:model="titulo" label="Título de la Meta" placeholder="Ej: Módulo A: UI/UX con Figma" />

                <flux:select wire:model="category_id" label="Materia / Categoría" placeholder="Selecciona una materia...">
                    @foreach($categorias as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="fecha_inicio" label="Fecha de Inicio" class="md:col-span-2" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="subtle" class="transition-all duration-150 hover:bg-zinc-200 dark:hover:bg-zinc-700 cursor-pointer">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveGoal" class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading wire:target="saveGoal" />
                    <span>{{ $editandoId ? 'Guardar Cambios' : 'Crear Meta' }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
