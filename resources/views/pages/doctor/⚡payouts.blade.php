<?php

use App\Actions\PrintReceipt;
use App\Models\Doctor;
use App\Models\DoctorPayout;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $expandedPayoutId = null;

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
    public function payouts()
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return collect();
        }

        return DoctorPayout::query()
            ->where('doctor_id', $doctor->id)
            ->with(['shift', 'paidBy', 'ledgerEntries.invoiceService.servicePrice.service', 'ledgerEntries.invoiceService.invoice.visit.patient'])
            ->latest('period_to')
            ->get();
    }

    public function expandPayout(int $id): void
    {
        $this->expandedPayoutId = $this->expandedPayoutId === $id ? null : $id;
    }

    public function printPayoutReceipt(int $payoutId): void
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return;
        }

        $payout = DoctorPayout::query()
            ->where('doctor_id', $doctor->id)
            ->with(['doctor', 'ledgerEntries.invoiceService.servicePrice.service'])
            ->find($payoutId);

        if ($payout !== null) {
            app(PrintReceipt::class)->forDoctorPayout($payout);
            $this->dispatch('payout-printed');
        }
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <flux:card class="p-5 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[560px]">
                        <thead><tr><th class="px-4 py-3"><flux:skeleton.line class="w-24" /></th><th class="px-4 py-3"><flux:skeleton.line class="w-20" /></th></tr></thead>
                        <tbody>@foreach (range(1, 5) as $i)<tr><td class="px-4 py-3"><flux:skeleton.line /></td><td class="px-4 py-3"><flux:skeleton.line class="w-16" /></td></tr>@endforeach</tbody>
                    </table>
                </div>
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Payouts') }}
    </flux:heading>

    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[560px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Period') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Amount') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Paid by') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->payouts as $payout)
                        <tr wire:key="payout-{{ $payout->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $payout->period_from?->format('M j, Y') }} – {{ $payout->period_to?->format('M j, Y') }}
                            </td>
                            <td class="px-5 py-4 text-sm text-right font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ number_format($payout->amount) }} PKR
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $payout->paidBy?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <flux:button size="sm" variant="ghost" wire:click="expandPayout({{ $payout->id }})" wire:loading.attr="disabled">
                                    {{ $this->expandedPayoutId === $payout->id ? __('Hide detail') : __('View detail') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="printPayoutReceipt({{ $payout->id }})" wire:loading.attr="disabled">
                                    {{ __('Print receipt') }}
                                </flux:button>
                            </td>
                        </tr>
                        @if ($this->expandedPayoutId === $payout->id)
                            <tr wire:key="payout-detail-{{ $payout->id }}" class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <td colspan="4" class="px-5 py-4">
                                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-900 p-4">
                                        <flux:heading size="sm" class="mb-3 text-zinc-800 dark:text-zinc-200">{{ __('Payout breakdown') }}</flux:heading>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-sm">
                                                <thead>
                                                    <tr class="border-b border-zinc-200 dark:border-zinc-600 text-zinc-500 dark:text-zinc-400">
                                                        <th class="pb-2 pr-4">{{ __('Date') }}</th>
                                                        <th class="pb-2 pr-4">{{ __('Patient') }}</th>
                                                        <th class="pb-2 pr-4">{{ __('Service') }}</th>
                                                        <th class="pb-2 pr-4 text-right">{{ __('Share') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                                    @foreach ($payout->ledgerEntries as $entry)
                                                        <tr>
                                                            <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $entry->invoiceService?->invoice?->created_at?->format('M j, Y') ?? '—' }}</td>
                                                            <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $entry->invoiceService?->invoice?->visit?->patient?->name ?? '—' }}</td>
                                                            <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $entry->invoiceService?->servicePrice?->service?->name ?? '—' }}</td>
                                                            <td class="py-2 pr-4 text-right font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($entry->share_amount) }} PKR</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">{{ __('No payouts yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
