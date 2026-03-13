<?php

use App\Models\Doctor;
use App\Models\ProcedureAdmission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $searchQuery = '';

    public string $searchDateFrom = '';

    public string $searchDateTo = '';

    #[Computed]
    public function doctor(): ?Doctor
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return $user->doctor?->status === 'active' ? $user->doctor : null;
    }

    #[Computed]
    public function procedures()
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return collect();
        }

        $query = ProcedureAdmission::query()
            ->where('operation_doctor_id', $doctor->id)
            ->with(['patient', 'invoice'])
            ->latest('created_at');

        if ($this->searchDateFrom !== '') {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($this->searchDateFrom));
        }
        if ($this->searchDateTo !== '') {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($this->searchDateTo)->endOfDay());
        }

        $term = trim($this->searchQuery);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->whereHas('patient', function (Builder $b) use ($term): void {
                    $b->where('name', 'like', '%'.$term.'%')
                        ->orWhere('mr_number', 'like', '%'.$term.'%');
                })
                    ->orWhere('package_name', 'like', '%'.$term.'%');
            });
        }

        return $query->get();
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
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Package</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Amounts</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach (range(1, 6) as $i)
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
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('My Procedures') }}
    </flux:heading>

    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Search by patient, MR# or package') }}</flux:label>
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
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Date from') }}</flux:label>
                    <flux:input
                        wire:model.live="searchDateFrom"
                        type="date"
                        class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                    />
                </flux:field>
            </div>
            <div>
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Date to') }}</flux:label>
                    <flux:input
                        wire:model.live="searchDateTo"
                        type="date"
                        class="bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100"
                    />
                </flux:field>
            </div>
        </div>
    </flux:card>

    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Date') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Patient') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Package') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">
                            {{ __('Full Price') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">
                            {{ __('Paid') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">
                            {{ __('Remaining') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Operation Date') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Room / Bed') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->procedures as $procedure)
                        @php
                            $invoice = $procedure->invoice;
                            $remaining = $invoice?->remainingBalance() ?? $procedure->full_price;
                        @endphp
                        <tr
                            wire:key="procedure-{{ $procedure->id }}"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                {{ $procedure->created_at->format('M j, Y') }}
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $procedure->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $procedure->patient?->name ?? '—' }}
                                <span class="block text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $procedure->patient?->mr_number ?? '—' }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $procedure->package_name }}
                            </td>
                            <td class="px-5 py-4 text-sm text-right text-zinc-700 dark:text-zinc-300">
                                Rs {{ number_format($procedure->full_price) }}
                            </td>
                            <td class="px-5 py-4 text-sm text-right text-zinc-700 dark:text-zinc-300">
                                Rs {{ number_format($invoice?->paid_amount ?? 0) }}
                            </td>
                            <td class="px-5 py-4 text-sm text-right">
                                @if ($remaining > 0)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">
                                        Rs {{ number_format($remaining) }}
                                    </span>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $procedure->operation_date?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                @php
                                    $roomBed = collect([$procedure->room, $procedure->bed])->filter()->implode(' / ');
                                @endphp
                                {{ $roomBed ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No procedures found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
