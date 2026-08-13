<?php

use App\Models\Penalty;
use Livewire\Volt\Component;
use Flux\Flux;
use Carbon\Carbon;

new class extends Component {
    public ?Penalty $penalty = null;

    public function mount()
    {
        // Obtenemos la penalización activa (no pagada)
        $this->penalty = Penalty::where('estado_pago', false)->first();

        if (!$this->penalty) {
            // Si por algún motivo entra aquí y no hay multas, lo regresamos al dashboard
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function payPenalty()
    {
        if ($this->penalty) {
            $this->penalty->update([
                'estado_pago' => true,
                'fecha_pago' => Carbon::now(),
            ]);

            \Masmerise\Toaster\Toaster::success('Multa liquidada. Sistema desbloqueado.');

            $this->redirectRoute('dashboard', navigate: true);
        }
    }
};
?>

<x-layouts.app title="Sistema Bloqueado">
    <div class="max-w-3xl mx-auto py-12">
        <flux:card class="border-red-500">
            <div class="flex items-center space-x-3 mb-6">
                <flux:icon name="exclamation-triangle" class="text-red-500 w-8 h-8" />
                <flux:heading size="xl" class="text-red-600">Acceso Bloqueado</flux:heading>
            </div>

            <flux:subheading class="mb-6 text-lg">
                No cumpliste con el mínimo de 16 horas requeridas. El acceso al sistema está restringido hasta que
                liquides tu deuda en tu cuenta de ahorro.
            </flux:subheading>

            @if($penalty)
            <div class="bg-red-50 dark:bg-red-900/20 p-6 rounded-lg mb-8 border border-red-200 dark:border-red-800">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Semana Evaluada</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($penalty->semana_inicio)->format('d M') }} al
                            {{ \Carbon\Carbon::parse($penalty->semana_fin)->format('d M, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Horas Faltantes</p>
                        <p class="font-semibold">{{ $penalty->horas_faltantes }} hrs</p>
                    </div>
                    <div class="col-span-2 mt-4 pt-4 border-t border-red-200 dark:border-red-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Monto a Transferir</p>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400">${{
                            number_format($penalty->monto_multa, 2) }} MXN</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="payPenalty" variant="danger" size="lg">
                    Confirmar Transferencia y Desbloquear
                </flux:button>
            </div>
            @endif
        </flux:card>
    </div>
</x-layouts.app>