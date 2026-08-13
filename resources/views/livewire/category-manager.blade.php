<?php
use App\Models\Category;
use Livewire\Volt\Component;
use Illuminate\Database\QueryException;

new class extends Component {
    public $nombre = '';
    public $editandoId = null;

    public function openCreateModal()
    {
        $this->reset(['nombre', 'editandoId']);
        $this->resetValidation();
        Flux::modal('category-modal')->show();
    }

    public function saveCategory()
    {
        $this->validate([
            'nombre' => 'required|string|max:255|unique:categories,nombre,' . $this->editandoId,
        ]);

        if ($this->editandoId) {
            Category::find($this->editandoId)->update(['nombre' => $this->nombre]);
            \Masmerise\Toaster\Toaster::success('Materia actualizada.');
        } else {
            Category::create(['nombre' => $this->nombre]);
            \Masmerise\Toaster\Toaster::success('Materia creada.');
        }

        $this->reset(['nombre', 'editandoId']);
        Flux::modal('category-modal')->close();
    }

    public function editCategory($id)
    {
        $categoria = Category::find($id);
        $this->nombre = $categoria->nombre;
        $this->editandoId = $categoria->id;
        $this->resetValidation();
        Flux::modal('category-modal')->show();
    }

    public function deleteCategory($id)
    {
        try {
            Category::find($id)->delete();
            \Masmerise\Toaster\Toaster::success('Materia eliminada correctamente.');
        } catch (QueryException $e) {
            \Masmerise\Toaster\Toaster::error('No puedes eliminar esta materia porque ya tiene metas asignadas.');
        }
    }

    public function with(): array
    {
        return [
            'categorias' => Category::withCount('goals')->orderBy('nombre')->get()
        ];
    }
};
?>

<div>
    <div class="max-w-4xl mx-auto py-6 space-y-6">
        <!-- Header de la sección -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Gestión de Materias</flux:heading>
                <flux:subheading>Catálogo global de asignaturas y módulos de aprendizaje.</flux:subheading>
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer">
                Nueva Materia
            </flux:button>
        </div>

        <!-- Listado / Catálogo -->
        <div class="space-y-3">
            @forelse($categorias as $cat)
            <flux:card class="flex justify-between items-center py-3 transition-all duration-200 hover:shadow-md" wire:key="category-{{ $cat->id }}">
                <div>
                    <flux:heading size="sm">{{ $cat->nombre }}</flux:heading>
                    <flux:subheading class="text-xs">En uso: {{ $cat->goals_count }} metas</flux:subheading>
                </div>

                <div class="flex items-center space-x-2">
                    <flux:button type="button" wire:click="editCategory({{ $cat->id }})" wire:loading.attr="disabled" wire:target="editCategory({{ $cat->id }})" size="sm" class="transition-transform duration-150 hover:scale-105 cursor-pointer flex items-center justify-center !text-white !bg-[#2299dd] hover:!bg-[#1b7eb8] border-none">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-white" wire:loading wire:target="editCategory({{ $cat->id }})" />
                        <svg wire:loading.remove wire:target="editCategory({{ $cat->id }})" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </flux:button>

                    <flux:button type="button" wire:click="deleteCategory({{ $cat->id }})" wire:loading.attr="disabled" wire:target="deleteCategory({{ $cat->id }})" size="sm" variant="danger" :disabled="$cat->goals_count > 0" title="{{ $cat->goals_count > 0 ? 'No se puede eliminar porque tiene metas asignadas' : 'Eliminar materia' }}" class="transition-transform duration-150 flex items-center justify-center !text-white {{ $cat->goals_count > 0 ? 'opacity-40 grayscale cursor-not-allowed pointer-events-none' : 'hover:scale-105 cursor-pointer' }}">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-white" wire:loading wire:target="deleteCategory({{ $cat->id }})" />
                        <svg wire:loading.remove wire:target="deleteCategory({{ $cat->id }})" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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
                Aún no has registrado materias. Haz clic en "Nueva Materia" para comenzar.
            </flux:card>
            @endforelse
        </div>
    </div>

    <!-- Modal Único para Crear / Editar -->
    <flux:modal name="category-modal" class="md:w-[500px] space-y-6">
        <div>
            <flux:heading size="lg">{{ $editandoId ? 'Editar Materia' : 'Nueva Materia' }}</flux:heading>
            <flux:subheading>{{ $editandoId ? 'Modifica el nombre de la asignatura seleccionada.' : 'Ingresa los detalles para dar de alta una nueva materia.' }}</flux:subheading>
        </div>

        <form wire:submit="saveCategory" class="space-y-6">
            <flux:input wire:model="nombre" label="Nombre de la Materia" placeholder="Ej. DevOps, UI/UX, Backend PHP" />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="subtle" class="transition-all duration-150 hover:bg-zinc-200 dark:hover:bg-zinc-700 cursor-pointer">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCategory" class="transition-transform duration-150 hover:scale-105 hover:shadow-md cursor-pointer flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading wire:target="saveCategory" />
                    <span>{{ $editandoId ? 'Guardar Cambios' : 'Crear Materia' }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
