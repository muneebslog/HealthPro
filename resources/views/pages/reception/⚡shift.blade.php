<?php

use App\Actions\PrintReceipt;
use App\Models\Queue;
use App\Models\Shift;
use App\Models\ShiftExpense;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showExpenseModal = false;

    public bool $showCloseShiftModal = false;

    public string $expenseAmount = '';

    public string $expenseDescription = '';

    public string $openingCash = '0';

    public string $cashInHand = '';

    #[Computed]
    public function currentShift(): ?Shift
    {
        return Shift::current();
    }

    #[Computed]
    public function expectedCashForCurrentShift(): ?float
    {
        $shift = $this->currentShift;
        if ($shift === null) {
            return null;
        }
        $opening = (float) $shift->opening_cash;
        $invoices = $shift->invoices()->sum('total_amount');
        $expenses = $shift->expenses()->sum('amount');
        $payouts = $shift->doctorPayouts()->sum('amount');

        return $opening + $invoices - $expenses - $payouts;
    }

    #[Computed]
    public function recentShifts()
    {
        return Shift::with(['openedBy', 'closedBy', 'expenses'])
            ->latest('opened_at')
            ->limit(10)
            ->get();
    }

    public function openShift(): void
    {
        if (Shift::current() !== null) {
            $this->addError('shift', __('A shift is already open.'));

            return;
        }

        $this->validate([
            'openingCash' => ['required', 'numeric', 'min:0'],
        ], [
            'openingCash.required' => __('Opening cash is required.'),
            'openingCash.numeric' => __('Opening cash must be a number.'),
            'openingCash.min' => __('Opening cash cannot be negative.'),
        ]);

        Shift::create([
            'opened_at' => now(),
            'opening_cash' => (float) $this->openingCash,
            'closed_at' => null,
            'opened_by' => auth()->id(),
            'closed_by' => null,
        ]);

        $this->openingCash = '0';
        $this->dispatch('shift-opened');
    }

    public function openCloseShiftModal(): void
    {
        $this->resetValidation();
        $this->cashInHand = '';
        $this->showCloseShiftModal = true;
    }

    public function closeShift(): void
    {
        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('shift', __('No shift is open.'));

            return;
        }

        $cashInHandValue = null;
        if (trim((string) $this->cashInHand) !== '') {
            $this->validate([
                'cashInHand' => ['numeric', 'min:0'],
            ], [
                'cashInHand.numeric' => __('Cash in hand must be a number.'),
                'cashInHand.min' => __('Cash in hand cannot be negative.'),
            ]);
            $cashInHandValue = (float) $this->cashInHand;
        }

        $expectedCash = $this->expectedCashForCurrentShift;

        $shift->update([
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'expected_cash' => $expectedCash,
            'cash_in_hand' => $cashInHandValue,
        ]);

        Queue::forShiftClose($shift->id)->active()->update([
            'status' => 'discontinued',
            'ended_at' => now(),
        ]);

        app(PrintReceipt::class)->forShiftClose($shift->fresh());

        $this->showCloseShiftModal = false;
        $this->cashInHand = '';
        $this->dispatch('shift-closed');
    }

    public function openExpenseModal(): void
    {
        $this->resetValidation();
        $this->expenseAmount = '';
        $this->expenseDescription = '';
        $this->showExpenseModal = true;
    }

    public function addExpense(): void
    {
        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('shift', __('No shift is open.'));

            return;
        }

        $this->validate([
            'expenseAmount' => ['required', 'numeric', 'min:0.01'],
            'expenseDescription' => ['required', 'string', 'max:500'],
        ], [
            'expenseAmount.required' => __('Amount is required.'),
            'expenseAmount.numeric' => __('Amount must be a number.'),
            'expenseAmount.min' => __('Amount must be at least 0.01.'),
            'expenseDescription.required' => __('Description is required.'),
            'expenseDescription.max' => __('Description may not exceed 500 characters.'),
        ]);

        ShiftExpense::create([
            'shift_id' => $shift->id,
            'amount' => (float) $this->expenseAmount,
            'description' => $this->expenseDescription,
            'recorded_by' => auth()->id(),
        ]);

        $this->showExpenseModal = false;
        $this->expenseAmount = '';
        $this->expenseDescription = '';
        $this->dispatch('expense-added');
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-24" />
            <flux:card class="p-6">
                <flux:skeleton.line class="mb-2 w-36" />
                <flux:skeleton.line class="mb-4 w-3/4" />
                <flux:skeleton class="h-10 w-24 rounded-lg" />
            </flux:card>
            <flux:card class="p-5">
                <div class="flex justify-between mb-4">
                    <flux:skeleton.line class="w-44" />
                    <flux:skeleton class="h-9 w-28 rounded-lg" />
                </div>
                <flux:skeleton.line class="mb-3 w-full" />
                <flux:skeleton.line class="mb-3 w-2/3" />
                <flux:skeleton.line class="w-1/2" />
            </flux:card>
            <flux:card class="p-5">
                <flux:skeleton.line class="mb-4 w-32" />
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[480px]">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Opened</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Opening cash</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Opened by</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Closed</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach (range(1, 5) as $i)
                                <tr>
                                    <td class="px-4 py-3"><flux:skeleton.line /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line class="w-20" /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line style="width: {{ 40 + $i * 10 }}%" /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line class="w-14" /></td>
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
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Shift') }}
    </flux:heading>

    @if ($this->currentShift === null)
        <flux:card class="p-6 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <flux:subheading class="mb-4 text-zinc-600 dark:text-zinc-400">
                {{ __('No shift open') }}
            </flux:subheading>
            <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Open a shift to start creating visits, invoices and queue tokens.') }}
            </p>
            <form wire:submit="openShift" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Opening cash') }}</flux:label>
                    <flux:input type="number" step="0.01" min="0" wire:model="openingCash" placeholder="0.00" />
                    <flux:error name="openingCash" />
                </flux:field>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Open shift') }}
                </flux:button>
            </form>
        </flux:card>
    @else
        <flux:card class="p-6 border border-emerald-200 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-900/20 rounded-xl shadow-sm">
            <flux:heading size="lg" class="mb-2 text-zinc-900 dark:text-zinc-50">
                {{ __('Shift open') }}
            </flux:heading>
            <flux:subheading class="mb-4 text-zinc-600 dark:text-zinc-400">
                {{ __('Opened by :name at :time', [
                    'name' => $this->currentShift->openedBy?->name ?? __('Unknown'),
                    'time' => $this->currentShift->opened_at->format('M j, Y h:i A'),
                ]) }}
            </flux:subheading>
            <p class="mb-4 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Opening cash') }}: {{ number_format($this->currentShift->opening_cash ?? 0, 2) }}
            </p>
            <flux:button variant="primary" wire:click="openCloseShiftModal" wire:loading.attr="disabled">
                {{ __('Close shift') }}
            </flux:button>
        </flux:card>

        <flux:card class="p-5 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Expenses (money out)') }}</flux:heading>
                <flux:button variant="primary" wire:click="openExpenseModal" wire:loading.attr="disabled">
                    {{ __('Log expense') }}
                </flux:button>
            </div>
            @php
                $currentShiftWithExpenses = $this->currentShift?->load('expenses.recordedBy');
            @endphp
            @if ($currentShiftWithExpenses && $currentShiftWithExpenses->expenses->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Time') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Recorded by') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach ($currentShiftWithExpenses->expenses as $expense)
                                <tr wire:key="expense-{{ $expense->id }}" class="text-sm text-zinc-700 dark:text-zinc-300">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $expense->created_at->format('M j, h:i A') }}</td>
                                    <td class="px-4 py-3">{{ $expense->description }}</td>
                                    <td class="px-4 py-3 font-medium text-red-600 dark:text-red-400">− {{ number_format($expense->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $expense->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Total expenses this shift') }}: <span class="text-red-600 dark:text-red-400">{{ number_format($currentShiftWithExpenses->expenses->sum('amount'), 2) }}</span>
                </p>
            @else
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No expenses logged for this shift yet.') }}</p>
            @endif
        </flux:card>
    @endif

    <flux:card class="p-5 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-4">{{ __('Recent shifts') }}</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[480px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Opened') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Opening cash') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Opened by') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Closed') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Closed by') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Expenses') }}
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Status') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->recentShifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}" class="text-sm text-zinc-700 dark:text-zinc-300">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $shift->opened_at->format('M j, Y h:i A') }}
                            </td>
                            <td class="px-4 py-3">
                                {{ number_format($shift->opening_cash ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $shift->openedBy?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $shift->closed_at?->format('M j, Y h:i A') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $shift->closed_at ? ($shift->closedBy?->name ?? '—') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ number_format($shift->expenses->sum('amount'), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($shift->closed_at === null)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                        {{ __('Open') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ __('Closed') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No shifts yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:modal wire:model="showExpenseModal" name="log-expense-modal" class="max-w-md" focusable>
        <form wire:submit="addExpense" class="space-y-4">
            <flux:heading size="lg">{{ __('Log expense') }}</flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">{{ __('Record money out for this shift.') }}</flux:subheading>
            <flux:field>
                <flux:label>{{ __('Amount') }}</flux:label>
                <flux:input type="number" step="0.01" min="0.01" wire:model="expenseAmount" placeholder="0.00" required />
                <flux:error name="expenseAmount" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="expenseDescription" placeholder="{{ __('e.g. Petty cash, supplies, refund...') }}" rows="3" required />
                <flux:error name="expenseDescription" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showExpenseModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Add expense') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCloseShiftModal" name="close-shift-modal" class="max-w-md" focusable>
        <form wire:submit="closeShift" class="space-y-4">
            <flux:heading size="lg">{{ __('Close shift') }}</flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">{{ __('Confirm and print shift summary.') }}</flux:subheading>
            @if ($this->expectedCashForCurrentShift !== null)
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Expected payment in cash') }}: {{ number_format($this->expectedCashForCurrentShift, 2) }}
                </p>
            @endif
            <flux:field>
                <flux:label>{{ __('Cash in hand') }}</flux:label>
                <flux:input type="number" step="0.01" min="0" wire:model="cashInHand" placeholder="0.00" />
                <flux:error name="cashInHand" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCloseShiftModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Close shift & print') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
