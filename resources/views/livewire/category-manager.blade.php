<?php
use App\Models\Category;
use Livewire\Volt\Component;
use Illuminate\Database\QueryException;

new class extends Component {
    public $nombre = '';
    public $editandoId = null;

    public function saveCategory()
    {
        $this->validate([
            'nombre' => 'required|string|max:255|unique:categories,nombre,' . $this->editandoId,
        ]);

        if ($this->editandoId) {
            Category::find($this->editandoId)->update(['nombre' => $this->nombre]);
            Flux::toast('Materia actualizada.');
        } else {
            Category::create(['nombre' => $this->nombre]);
            Flux::toast('Materia creada.');
        }

        $this->reset(['nombre', 'editandoId']);
    }

    public function editCategory($id)
    {
        $categoria = Category::find($id);
        $this->nombre = $categoria->nombre;
        $this->editandoId = $categoria->id;
    }

    public function cancelEdit()
    {
        $this->reset(['nombre', 'editandoId']);
    }

    public function deleteCategory($id)
    {
        try {
            Category::find($id)->delete();
            Flux::toast('Materia eliminada correctamente.');
        } catch (QueryException $e) {
            Flux::toast('No puedes eliminar esta materia porque ya tiene metas asignadas.', variant: 'danger');
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
    <div class="max-w-4xl mx-auto py-6">
        <flux:card>
            <flux:heading size="lg">{{ $editandoId ? 'Editar Materia' : 'Nueva Materia' }}</flux:heading>

            <form wire:submit="saveCategory" class="mt-4 space-y-4">
                <flux:input wire:model="nombre" label="Nombre de la Materia (Ej. DevOps, UI/UX)" />

                <div class="flex justify-end space-x-2 mt-4">
                    @if($editandoId)
                    <flux:button wire:click="cancelEdit" variant="subtle">Cancelar</flux:button>
                    @endif
                    <flux:button type="submit" variant="primary">{{ $editandoId ? 'Actualizar' : 'Guardar' }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <div class="mt-8">
            <flux:heading size="md" class="mb-4">Catálogo de Materias</flux:heading>

            <div class="space-y-3">
                @forelse($categorias as $cat)
                <flux:card class="flex justify-between items-center py-3">
                    <div>
                        <flux:heading size="sm">{{ $cat->nombre }}</flux:heading>
                        <flux:subheading class="text-xs">En uso: {{ $cat->goals_count }} metas</flux:subheading>
                    </div>

                    <div class="flex space-x-2">
                        <flux:button wire:click="editCategory({{ $cat->id }})" size="sm" variant="subtle"
                            icon="pencil" />

                        @if($cat->goals_count == 0)
                        <flux:button wire:click="deleteCategory({{ $cat->id }})" size="sm" variant="danger"
                            icon="trash" />
                        @endif
                    </div>
                </flux:card>
                @empty
                <flux:card class="text-center text-gray-500">
                    Aún no has registrado materias.
                </flux:card>
                @endforelse
            </div>
        </div>
    </div>
</div>
