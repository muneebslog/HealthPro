<?php

use App\Actions\PrintReceipt;
use App\Models\Doctor;
use App\Models\DoctorPayout;
use App\Models\DoctorPayoutLedger;
use App\Models\InvoiceService;
use App\Models\Shift;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedDoctorId = null;

    #[Computed]
    public function doctorsForPayout()
    {
        return Doctor::query()
            ->where('is_on_payroll', false)
            ->whereNotNull('payout_duration')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedDoctor(): ?Doctor
    {
        if ($this->selectedDoctorId === null) {
            return null;
        }

        return Doctor::find($this->selectedDoctorId);
    }

    #[Computed]
    public function unpaidLines()
    {
        $doctor = $this->selectedDoctor;
        if ($doctor === null) {
            return collect();
        }

        $periodFrom = now()->copy()->subDays($doctor->payout_duration)->startOfDay();
        $periodTo = now()->copy()->endOfDay();

        $paidIds = DoctorPayoutLedger::query()->select('invoice_service_id')->pluck('invoice_service_id');

        $lines = InvoiceService::query()
            ->whereHas('servicePrice', fn ($q) => $q->where('doctor_id', $doctor->id))
            ->whereHas('invoice', fn ($q) => $q->whereBetween('created_at', [$periodFrom, $periodTo]))
            ->when($paidIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $paidIds))
            ->with(['servicePrice.service', 'servicePrice.doctor', 'invoice.visit.patient'])
            ->orderBy('id')
            ->get();

        return $lines->map(function (InvoiceService $invSvc) {
            $sp = $invSvc->servicePrice;
            $share = 0;
            if ($sp && $sp->price > 0 && $sp->doctor_share !== null) {
                $share = (int) round(($sp->doctor_share / $sp->price) * $invSvc->final_amount);
            }

            return (object) [
                'invoice_service' => $invSvc,
                'share_amount' => $share,
            ];
        });
    }

    #[Computed]
    public function totalShare(): int
    {
        return $this->unpaidLines->sum('share_amount');
    }

    #[Computed]
    public function periodFrom(): ?CarbonInterface
    {
        $doctor = $this->selectedDoctor;
        if ($doctor === null) {
            return null;
        }

        return now()->copy()->subDays($doctor->payout_duration)->startOfDay();
    }

    #[Computed]
    public function periodTo(): ?CarbonInterface
    {
        if ($this->selectedDoctor === null) {
            return null;
        }

        return now()->copy()->endOfDay();
    }

    public function payDoctor(): void
    {
        $doctor = $this->selectedDoctor;
        if ($doctor === null) {
            $this->addError('doctor', __('Please select a doctor.'));

            return;
        }

        $lines = $this->unpaidLines;
        if ($lines->isEmpty() || $this->totalShare <= 0) {
            $this->addError('payout', __('No unpaid share to pay for this period.'));

            return;
        }

        $currentShift = Shift::current();
        if ($currentShift === null) {
            $this->addError('shift', __('Open a shift before recording a doctor payout.'));

            return;
        }

        $periodFrom = $this->periodFrom;
        $periodTo = $this->periodTo;
        if ($periodFrom === null || $periodTo === null) {
            return;
        }

        $payout = DoctorPayout::create([
            'doctor_id' => $doctor->id,
            'amount' => $this->totalShare,
            'period_from' => $periodFrom->toDateString(),
            'period_to' => $periodTo->toDateString(),
            'shift_id' => $currentShift->id,
            'paid_by' => auth()->id(),
        ]);

        foreach ($lines as $row) {
            DoctorPayoutLedger::create([
                'doctor_payout_id' => $payout->id,
                'invoice_service_id' => $row->invoice_service->id,
                'share_amount' => $row->share_amount,
            ]);
        }

        app(PrintReceipt::class)->forDoctorPayout($payout->load(['doctor', 'ledgerEntries.invoiceService.servicePrice.service']));

        session()->flash('payout_recorded', true);
        $this->redirect(route('reception.payout'), navigate: true);
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <flux:card class="p-5">
                <flux:skeleton.line class="mb-2 w-20" />
                <flux:skeleton class="h-10 w-full rounded-lg" />
            </flux:card>
            <flux:card class="p-5 overflow-hidden">
                <flux:skeleton.line class="mb-4 w-32" />
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[640px]">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Date</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Patient</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Service</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Amount</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach (range(1, 5) as $i)
                                <tr>
                                    <td class="px-4 py-3"><flux:skeleton.line /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line style="width: {{ 60 + $i * 8 }}%" /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line /></td>
                                    <td class="px-4 py-3"><flux:skeleton.line class="w-16" /></td>
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
        {{ __('Doctor payout') }}
    </flux:heading>

    @if (session('payout_recorded'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Payout recorded successfully.') }}
        </flux:callout>
    @endif

    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <flux:field>
            <flux:label class="text-zinc-700 dark:text-zinc-300">{{ __('Doctor') }}</flux:label>
            <flux:select wire:model.live="selectedDoctorId" label="{{ __('Choose doctor…') }}">
                <flux:select.option value="">{{ __('Choose doctor…') }}</flux:select.option>
                @foreach ($this->doctorsForPayout as $doctor)
                    <flux:select.option value="{{ $doctor->id }}">{{ $doctor->name }} ({{ $doctor->payout_duration }} {{ __('days') }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="doctor" />
            @if ($this->doctorsForPayout->isEmpty())
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No share-based doctors. Only doctors not on payroll with a payout duration are listed.') }}</p>
            @endif
        </flux:field>
    </flux:card>

    @if ($this->selectedDoctor !== null)
        @if ($this->selectedDoctor->payout_duration === null)
            <flux:card class="p-5 border border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/20 rounded-xl">
                <flux:text class="text-amber-800 dark:text-amber-200">{{ __('This doctor has no payout duration set. Set it in Cruds to enable payouts.') }}</flux:text>
            </flux:card>
        @else
            <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                    {{ __('Period') }}: {{ $this->periodFrom?->format('M j, Y') }} – {{ $this->periodTo?->format('M j, Y') }}
                </p>

                @if ($this->unpaidLines->isEmpty())
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No unpaid invoice services for this doctor in this period.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[640px]">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Patient / Invoice') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Final amount') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">{{ __('Doctor share') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @foreach ($this->unpaidLines as $row)
                                    @php
                                        $invSvc = $row->invoice_service;
                                        $invoice = $invSvc->invoice;
                                    @endphp
                                    <tr wire:key="line-{{ $invSvc->id }}" class="text-sm text-zinc-700 dark:text-zinc-300">
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $invoice?->created_at?->format('M j, Y H:i') ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            {{ $invoice?->visit?->patient?->name ?? '—' }}
                                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">#{{ $invoice?->id ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">{{ $invSvc->servicePrice?->service?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($invSvc->final_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-medium">{{ number_format($row->share_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 font-semibold">
                                    <td colspan="4" class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ __('Total doctor share') }}</td>
                                    <td class="px-4 py-4 text-right text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalShare) }} <span class="text-zinc-500 dark:text-zinc-400 text-xs">PKR</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <flux:button
                            variant="primary"
                            wire:click="payDoctor"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Pay doctor') }}
                        </flux:button>
                        <flux:error name="payout" />
                        <flux:error name="shift" />
                    </div>
                @endif
            </flux:card>
        @endif
    @endif
</div>
