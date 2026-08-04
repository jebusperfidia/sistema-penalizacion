<?php

use App\Models\Goal;
use App\Models\Category;
use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component {
    public $titulo = '';
    public $category_id = '';
    public $fecha_inicio = '';

    public function saveGoal()
    {
        // Reglas estrictas de validación
        $this->validate([
            'titulo' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id', // Validamos contra la BD
            'fecha_inicio' => 'required|date',
        ]);

        Goal::create([
            'titulo' => $this->titulo,
            'category_id' => $this->category_id,
            'fecha_inicio' => $this->fecha_inicio,
        ]);

        $this->reset(['titulo', 'category_id', 'fecha_inicio']);

        Flux::toast('Meta registrada con éxito.');
    }

    public function with(): array
    {
       return [
    'categorias' => Category::orderBy('nombre')->get(),
    ];
    }
};
?>

<div>
    <div class="max-w-4xl mx-auto py-6">
        <flux:card>
            <flux:heading size="lg">Nueva Meta de Aprendizaje</flux:heading>
            <flux:subheading>Asigna un nuevo bloque de estudio. Se activará a partir de su fecha de inicio.
            </flux:subheading>

            <form wire:submit="saveGoal" class="mt-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <flux:input wire:model="titulo" label="Título de la Meta"
                        placeholder="Ej: Módulo A: UI/UX con Figma" />

                    <flux:select wire:model="category_id" label="Materia / Categoría"
                        placeholder="Selecciona una materia...">
                        @foreach($categorias as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input type="date" wire:model="fecha_inicio" label="Fecha de Inicio" />
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Guardar Meta</flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
