<?php

use App\Actions\PrintReceipt;
use App\Models\Invoice;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $searchQuery = '';

    public string $searchDateFrom = '';

    public string $searchDateTo = '';

    public bool $filterByCurrentShift = true;

    public ?int $currentShiftId = null;

    public function mount(): void
    {
        $current = Shift::current();
        $this->currentShiftId = $current?->id;
    }

    #[Computed]
    public function invoices()
    {
        $query = Invoice::query()
            ->with([
                'visit.patient',
                'visit.queueTokens.queue',
                'invoiceServices.servicePrice.service',
                'invoiceServices.servicePrice.doctor',
            ])
            ->latest('created_at');

        if ($this->currentShiftId !== null && $this->filterByCurrentShift) {
            $query->where('shift_id', $this->currentShiftId);
        }

        if ($this->searchDateFrom !== '') {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($this->searchDateFrom));
        }
        if ($this->searchDateTo !== '') {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($this->searchDateTo));
        }

        $term = trim($this->searchQuery);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->whereHas('visit.patient', function (Builder $b) use ($term): void {
                    $b->where('name', 'like', '%'.$term.'%');
                })
                    ->orWhereHas('invoiceServices.servicePrice.doctor', function (Builder $b) use ($term): void {
                        $b->where('name', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('invoiceServices.servicePrice.service', function (Builder $b) use ($term): void {
                        $b->where('name', 'like', '%'.$term.'%');
                    });
            });
        }

        return $query->get();
    }

    #[Computed]
    public function grandTotal(): int
    {
        return $this->invoices->sum('total_amount');
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
            <flux:skeleton.line class="h-8 w-28" />
            <flux:card class="p-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><flux:skeleton.line class="mb-2 w-24" /><flux:skeleton class="h-10 w-full rounded-lg" /></div>
                    <div><flux:skeleton.line class="mb-2 w-28" /><flux:skeleton class="h-10 w-full rounded-lg" /></div>
                    <div><flux:skeleton.line class="mb-2 w-20" /><flux:skeleton class="h-10 w-full rounded-lg" /></div>
                </div>
            </flux:card>
            <flux:card class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[720px]">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Date</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Patient</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Services</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Total</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach (range(1, 8) as $i)
                                <tr>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-24" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line style="width: {{ 50 + $i * 5 }}%" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-20" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-16" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-20" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
            {{ __('Invoices') }}
        </flux:heading>
    </div>

    {{-- Search & filters --}}
    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Search by patient, doctor or service') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="searchQuery"
                        type="search"
                        placeholder="{{ __('Type to search...') }}"
                        icon="magnifying-glass"
                        class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500"
                    />
                </flux:field>
            </div>
            <div>
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Date & time from') }}</flux:label>
                    <flux:input
                        wire:model.live="searchDateFrom"
                        type="datetime-local"
                        class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                    />
                </flux:field>
            </div>
            <div>
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Date & time to') }}</flux:label>
                    <flux:input
                        wire:model.live="searchDateTo"
                        type="datetime-local"
                        class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                    />
                </flux:field>
            </div>
            @if ($currentShiftId !== null)
                <div class="flex items-end pb-1">
                    <flux:checkbox wire:model.live="filterByCurrentShift" :label="__('Current shift only')" />
                </div>
            @endif
        </div>
    </flux:card>

    {{-- Invoices table --}}
    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Date & time') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Patient') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Services') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Doctor(s)') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">
                            {{ __('Invoice total') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->invoices as $invoice)
                        <tr
                            wire:key="invoice-{{ $invoice->id }}"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                {{ $invoice->created_at->format('M j, Y') }}
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $invoice->visit?->patient?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($invoice->invoiceServices as $invSvc)
                                        @php
                                            $svc = $invSvc->servicePrice?->service;
                                            $token = $invoice->visit?->queueTokens->first(fn ($t) => $t->queue
                                                && (int) $t->queue->service_id === (int) ($invSvc->servicePrice?->service_id)
                                                && $t->queue->doctor_id === $invSvc->servicePrice?->doctor_id);
                                        @endphp
                                        @if ($svc)
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 border border-emerald-200/60 dark:border-emerald-700/50"
                                            >
                                                {{ $svc->name }}
                                                @if ($token)
                                                    <span class="ml-1 font-semibold tabular-nums">#{{ $token->token_number }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    @endforeach
                                    @if ($invoice->invoiceServices->isEmpty())
                                        <span class="text-zinc-400 dark:text-zinc-500 text-sm">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($invoice->invoiceServices->pluck('servicePrice.doctor')->filter()->unique('id') as $doctor)
                                        @if ($doctor)
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200 border border-sky-200/60 dark:border-sky-700/50"
                                            >
                                                {{ $doctor->name }}
                                            </span>
                                        @endif
                                    @endforeach
                                    @if ($invoice->invoiceServices->pluck('servicePrice.doctor')->filter()->isEmpty())
                                        <span class="text-zinc-400 dark:text-zinc-500 text-sm">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ number_format($invoice->total_amount) }}
                                </span>
                                <span class="text-zinc-500 dark:text-zinc-400 text-xs ml-0.5">PKR</span>
                            </td>
                            <td class="px-5 py-4">
                                <flux:button size="sm" variant="ghost" wire:click="printInvoiceReceipt({{ $invoice->id }})" wire:loading.attr="disabled">
                                    {{ __('Print receipt') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No invoices found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->invoices->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 font-semibold">
                            <td colspan="4" class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ __('Grand total') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="text-zinc-900 dark:text-zinc-100">{{ number_format($this->grandTotal) }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400 text-xs ml-0.5">PKR</span>
                            </td>
                            <td class="px-5 py-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </flux:card>
</div>
