<?php

use App\Models\ReceiptPrint;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    #[Computed]
    public function prints(): Collection
    {
        $query = ReceiptPrint::query()
            ->with(['invoice.visit.patient', 'shift', 'doctorPayout.doctor', 'printedBy'])
            ->where('printed_at', '>=', $this->dateFrom.' 00:00:00')
            ->where('printed_at', '<=', $this->dateTo.' 23:59:59')
            ->latest('printed_at');

        return $query->get();
    }

    /**
     * Keys (print_type + source id) that appear more than once (duplicates).
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function duplicateKeys(): Collection
    {
        $keys = $this->prints->map(fn (ReceiptPrint $p) => self::printKey($p));
        $counts = $keys->countBy();

        return $counts->filter(fn (int $c) => $c > 1)->keys();
    }

    public static function printKey(ReceiptPrint $p): string
    {
        $parts = [$p->print_type];
        $parts[] = $p->invoice_id ?? 'n';
        $parts[] = $p->shift_id ?? 'n';
        $parts[] = $p->doctor_payout_id ?? 'n';

        return implode(':', $parts);
    }

    public function isDuplicate(ReceiptPrint $p): bool
    {
        return $this->duplicateKeys->contains(self::printKey($p));
    }

    public function descriptionFor(ReceiptPrint $p): string
    {
        return match ($p->print_type) {
            'invoice' => $p->invoice
                ? __('Invoice #:id — :patient', [
                    'id' => $p->invoice_id,
                    'patient' => $p->invoice->visit?->patient?->name ?? '—',
                ])
                : __('Invoice #:id', ['id' => $p->invoice_id]),
            'shift_close' => $p->shift
                ? __('Shift closed :date', ['date' => $p->shift->closed_at?->format('M j, Y H:i') ?? $p->printed_at->format('M j, Y H:i')])
                : __('Shift #:id', ['id' => $p->shift_id]),
            'doctor_payout' => $p->doctorPayout
                ? __('Payout :doctor — :amount PKR', [
                    'doctor' => $p->doctorPayout->doctor?->name ?? '—',
                    'amount' => number_format($p->doctorPayout->amount),
                ])
                : __('Payout #:id', ['id' => $p->doctor_payout_id]),
            default => $p->print_type.' #'.$p->id,
        };
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
            {{ __('Printed receipts') }}
        </flux:heading>
    </div>

    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:field>
                <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('From date') }}</flux:label>
                <flux:input
                    wire:model.live="dateFrom"
                    type="date"
                    class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                />
            </flux:field>
            <flux:field>
                <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('To date') }}</flux:label>
                <flux:input
                    wire:model.live="dateTo"
                    type="date"
                    class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                />
            </flux:field>
        </div>
        @if($this->duplicateKeys->isNotEmpty())
            <div class="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
                <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                <span>{{ __('Rows highlighted in amber are duplicate prints (same receipt printed more than once).') }}</span>
            </div>
        @endif
    </flux:card>

    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Printed at') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Type') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Description') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Printed by') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Duplicate') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->prints as $print)
                        <tr
                            wire:key="print-{{ $print->id }}"
                            @class([
                                'transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => !$this->isDuplicate($print),
                                'bg-amber-100/80 dark:bg-amber-900/30 border-l-4 border-amber-500' => $this->isDuplicate($print),
                            ])
                        >
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                {{ $print->printed_at->format('M j, Y') }}
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $print->printed_at->format('H:i:s') }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <flux:badge color="zinc" size="sm">
                                    {{ $print->print_type }}
                                </flux:badge>
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $this->descriptionFor($print) }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $print->printedBy?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                @if($this->isDuplicate($print))
                                    <flux:badge color="amber" size="sm">{{ __('Duplicate') }}</flux:badge>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No prints in this date range.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
