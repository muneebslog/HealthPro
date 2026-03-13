<?php

use App\Models\Doctor;
use App\Models\InvoiceService;
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
    public function invoiceLines()
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return collect();
        }

        $query = InvoiceService::query()
            ->whereHas('servicePrice', fn (Builder $q) => $q->where('doctor_id', $doctor->id))
            ->with([
                'invoice.visit.patient',
                'invoice.visit.queueTokens.queue',
                'servicePrice.service',
                'servicePrice.doctor',
            ])
            ->latest('id');

        if ($this->searchDateFrom !== '') {
            $query->whereHas('invoice', fn (Builder $q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($this->searchDateFrom)));
        }
        if ($this->searchDateTo !== '') {
            $query->whereHas('invoice', fn (Builder $q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($this->searchDateTo)->endOfDay()));
        }

        $term = trim($this->searchQuery);
        if ($term !== '') {
            $query->whereHas('invoice.visit.patient', function (Builder $q) use ($term): void {
                $q->where('name', 'like', '%'.$term.'%');
            });
        }

        $lines = $query->get();

        return $lines->map(function (InvoiceService $invSvc) {
            $sp = $invSvc->servicePrice;
            $share = 0;
            if ($sp && $sp->price > 0 && $sp->doctor_share !== null) {
                $share = (int) round(($sp->doctor_share / $sp->price) * $invSvc->final_amount);
            }
            $hospitalShare = $invSvc->final_amount - $share;

            return (object) [
                'invoice_service' => $invSvc,
                'doctor_share' => $share,
                'hospital_share' => $hospitalShare,
            ];
        });
    }

    #[Computed]
    public function totalDoctorShare(): int
    {
        return $this->invoiceLines->sum('doctor_share');
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
                        <thead><tr><th class="px-5 py-4"><flux:skeleton.line class="w-24" /></th><th class="px-5 py-4"><flux:skeleton.line class="w-32" /></th></tr></thead>
                        <tbody>@foreach (range(1, 6) as $i)<tr><td class="px-5 py-4"><flux:skeleton.line class="w-24" /></td><td class="px-5 py-4"><flux:skeleton.line class="w-20" /></td></tr>@endforeach</tbody>
                    </table>
                </div>
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Invoices & shares') }}
    </flux:heading>

    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:field>
                    <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Search by patient') }}</flux:label>
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
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Patient') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Service') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Token') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Amount') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Your share') }}</th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Hospital') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->invoiceLines as $row)
                        @php
                            $inv = $row->invoice_service->invoice;
                            $token = $inv?->visit?->queueTokens->first(fn ($t) => $t->queue
                                && (int) $t->queue->service_id === (int) ($row->invoice_service->servicePrice?->service_id)
                                && $t->queue->doctor_id === $row->invoice_service->servicePrice?->doctor_id);
                        @endphp
                        <tr wire:key="line-{{ $row->invoice_service->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                {{ $row->invoice_service->invoice?->created_at?->format('M j, Y') }}
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $row->invoice_service->invoice?->created_at?->format('h:i A') }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $row->invoice_service->invoice?->visit?->patient?->name ?? '—' }}</td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $row->invoice_service->servicePrice?->service?->name ?? '—' }}</td>
                            <td class="px-5 py-4 text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ $token ? '#'.$token->token_number : '—' }}</td>
                            <td class="px-5 py-4 text-sm text-right text-zinc-700 dark:text-zinc-300">{{ number_format($row->invoice_service->final_amount) }} PKR</td>
                            <td class="px-5 py-4 text-sm text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($row->doctor_share) }} PKR</td>
                            <td class="px-5 py-4 text-sm text-right text-zinc-600 dark:text-zinc-400">{{ number_format($row->hospital_share) }} PKR</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">{{ __('No invoice lines found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->invoiceLines->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 font-semibold">
                            <td colspan="5" class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ __('Total your share (filtered)') }}</td>
                            <td class="px-5 py-4 text-right text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalDoctorShare) }} PKR</td>
                            <td class="px-5 py-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </flux:card>
</div>
