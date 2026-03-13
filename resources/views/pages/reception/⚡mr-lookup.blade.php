<?php

use App\Actions\PrintReceipt;
use App\Models\Invoice;
use App\Models\Patient;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $mrSearch = '';

    #[Computed]
    public function patient(): ?Patient
    {
        $p = Patient::findByMrNumber($this->mrSearch);
        if ($p !== null) {
            $p->loadMissing('family');
        }

        return $p;
    }

    #[Computed]
    public function visits()
    {
        $p = $this->patient;
        if ($p === null) {
            return collect();
        }

        return $p->visits()
            ->with(['shift', 'invoice'])
            ->latest('created_at')
            ->get();
    }

    #[Computed]
    public function invoices()
    {
        $p = $this->patient;
        if ($p === null) {
            return collect();
        }

        return $p->invoices()
            ->with([
                'visit',
                'invoiceServices.servicePrice.service',
                'invoiceServices.servicePrice.doctor',
            ])
            ->latest('created_at')
            ->get();
    }

    public function printInvoiceReceipt(int $invoiceId): void
    {
        $invoice = Invoice::with([
            'visit.queueTokens.queue',
            'visit.patient.family',
            'invoiceServices.servicePrice.service',
            'invoiceServices.servicePrice.doctor',
        ])->find($invoiceId);
        if ($invoice !== null) {
            app(PrintReceipt::class)->forInvoice($invoice);
        }
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <flux:card class="p-5">
                <flux:skeleton.line class="mb-2 w-24" />
                <flux:skeleton class="h-10 w-full max-w-md rounded-lg" />
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
            {{ __('MR Lookup') }}
        </flux:heading>
    </div>

    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <flux:field>
            <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('MR number') }}</flux:label>
            <flux:input
                wire:model.live.debounce.300ms="mrSearch"
                type="text"
                placeholder="{{ __('Enter MR number (e.g. MR-004291 or 4291)') }}"
                icon="magnifying-glass"
                class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 max-w-md"
            />
        </flux:field>
    </flux:card>

    @if ($this->patient !== null)
        <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
            <flux:heading size="lg" class="mb-4 text-zinc-900 dark:text-zinc-50">{{ __('Patient summary') }}</flux:heading>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->patient->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('MR#') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->patient->mr_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('DOB') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $this->patient->dob?->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Gender') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ ucfirst($this->patient->gender ?? '') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Phone') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $this->patient->family?->phone ?? '—' }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <flux:heading size="lg" class="p-5 pb-2 text-zinc-900 dark:text-zinc-50">{{ __('Visits') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[400px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Date & time') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Invoice') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @forelse ($this->visits as $visit)
                            <tr wire:key="visit-{{ $visit->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $visit->created_at->format('M j, Y') }}
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $visit->created_at->format('h:i A') }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                                        {{ ucfirst($visit->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                    @if ($visit->invoice)
                                        {{ number_format($visit->invoice->total_amount) }} PKR
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No visits found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>

        <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <flux:heading size="lg" class="p-5 pb-2 text-zinc-900 dark:text-zinc-50">{{ __('Invoices') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[500px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Date & time') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @forelse ($this->invoices as $invoice)
                            <tr wire:key="invoice-{{ $invoice->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $invoice->created_at->format('M j, Y') }}
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->created_at->format('h:i A') }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ number_format($invoice->total_amount) }} PKR
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <flux:button size="sm" variant="ghost" wire:click="printInvoiceReceipt({{ $invoice->id }})" wire:loading.attr="disabled">
                                        {{ __('Print receipt') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No invoices found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @elseif (trim($this->mrSearch) !== '')
        <flux:card class="p-8 text-center border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900">
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('No patient found for this MR number.') }}</p>
        </flux:card>
    @endif
</div>
