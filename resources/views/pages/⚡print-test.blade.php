<?php

use App\Actions\PrintReceipt;
use Livewire\Component;

new class extends Component
{
    public ?string $printError = null;

    public function printTest(string $target = 'port'): void
    {
        $this->printError = null;
        try {
            $printerName = $target === 'name'
                ? config('printing.printer_name') ?? 'Tysso Thermal Receipt Printer'
                : null;
            app(PrintReceipt::class)->printTest($printerName);
        } catch (\Throwable $e) {
            $this->printError = $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Print test failed', [
                'target' => $target,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
};
?>

<div class="p-6 space-y-6">
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Printer test') }}
    </flux:heading>

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Try both options: COM port and printer name.') }}
    </p>

    @if($printError)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:heading size="md">{{ __('Print failed') }}</flux:heading>
            <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 dark:bg-zinc-800 p-3 text-sm">{{ e($printError) }}</pre>
        </flux:callout>
    @endif

    <div class="flex flex-wrap gap-3">
        <flux:button variant="primary" wire:click="printTest('port')" wire:loading.attr="disabled">
            <flux:icon name="printer" class="size-4" />
            {{ __('Print test (COM :port)', ['port' => config('printing.default_printer_port', 'COM8')]) }}
        </flux:button>
        <flux:button variant="outline" wire:click="printTest('name')" wire:loading.attr="disabled">
            <flux:icon name="printer" class="size-4" />
            {{ __('Print test (by name)') }}
        </flux:button>
    </div>
</div>
