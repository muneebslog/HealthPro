<?php

use App\Models\Doctor;
use App\Models\DoctorPayout;
use App\Models\InvoiceService;
use App\Models\Queue;
use App\Models\QueueToken;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
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
    public function todayTokensSummary(): array
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return ['total' => 0, 'arrived' => 0, 'completed' => 0, 'pending' => 0];
        }

        $queue = Queue::query()
            ->active()
            ->where('doctor_id', $doctor->id)
            ->where('service_id', Queue::APPOINTMENT_SERVICE_ID)
            ->first();

        if (! $queue) {
            return ['total' => 0, 'arrived' => 0, 'completed' => 0, 'pending' => 0];
        }

        $tokens = $queue->queueTokens;
        $arrived = $tokens->whereIn('status', ['arrived', 'called', 'completed'])->count();
        $completed = $tokens->where('status', 'completed')->count();
        $pending = $tokens->whereIn('status', ['reserved', 'available'])->count();

        return [
            'total' => $tokens->count(),
            'arrived' => $arrived,
            'completed' => $completed,
            'pending' => $pending,
        ];
    }

    #[Computed]
    public function currentPeriodShare(): int
    {
        $doctor = $this->doctor;
        if (! $doctor || $doctor->is_on_payroll || ! $doctor->payout_duration) {
            return 0;
        }

        $periodFrom = Carbon::now()->subDays($doctor->payout_duration)->startOfDay();
        $periodTo = Carbon::now()->endOfDay();
        $paidIds = \App\Models\DoctorPayoutLedger::query()->select('invoice_service_id')->pluck('invoice_service_id');

        $lines = InvoiceService::query()
            ->whereHas('servicePrice', fn ($q) => $q->where('doctor_id', $doctor->id))
            ->whereHas('invoice', fn ($q) => $q->whereBetween('created_at', [$periodFrom, $periodTo]))
            ->when($paidIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $paidIds))
            ->with('servicePrice')
            ->get();

        $total = 0;
        foreach ($lines as $invSvc) {
            $sp = $invSvc->servicePrice;
            if ($sp && $sp->price > 0 && $sp->doctor_share !== null) {
                $total += (int) round(($sp->doctor_share / $sp->price) * $invSvc->final_amount);
            }
        }

        return $total;
    }

    #[Computed]
    public function lastPayout(): ?DoctorPayout
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return null;
        }

        return DoctorPayout::query()
            ->where('doctor_id', $doctor->id)
            ->latest('period_to')
            ->first();
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="p-5"><flux:skeleton.line class="mb-2 w-24" /><flux:skeleton class="h-10 w-20" /></flux:card>
                <flux:card class="p-5"><flux:skeleton.line class="mb-2 w-24" /><flux:skeleton class="h-10 w-20" /></flux:card>
                <flux:card class="p-5"><flux:skeleton.line class="mb-2 w-24" /><flux:skeleton class="h-10 w-20" /></flux:card>
                <flux:card class="p-5"><flux:skeleton.line class="mb-2 w-24" /><flux:skeleton class="h-10 w-20" /></flux:card>
            </div>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Dashboard') }}
    </flux:heading>

    @if ($this->doctor)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tokens today') }}</flux:text>
                <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->todayTokensSummary['total'] }}</flux:heading>
                <flux:link href="{{ route('doctor.appointments') }}" wire:navigate class="mt-2 text-sm">
                    {{ __('View queue') }}
                </flux:link>
            </flux:card>
            <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending (today)') }}</flux:text>
                <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->todayTokensSummary['pending'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unpaid share (current period)') }}</flux:text>
                <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ number_format($this->currentPeriodShare) }} PKR</flux:heading>
                <flux:link href="{{ route('doctor.invoices') }}" wire:navigate class="mt-2 text-sm">
                    {{ __('Invoices & shares') }}
                </flux:link>
            </flux:card>
            <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Last payout') }}</flux:text>
                @if ($this->lastPayout)
                    <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ number_format($this->lastPayout->amount) }} PKR</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $this->lastPayout->period_to?->format('M j, Y') }}</flux:text>
                    <flux:link href="{{ route('doctor.payouts') }}" wire:navigate class="mt-2 block text-sm">
                        {{ __('Payout history') }}
                    </flux:link>
                @else
                    <flux:heading size="xl" class="mt-1 text-zinc-500 dark:text-zinc-400">—</flux:heading>
                    <flux:link href="{{ route('doctor.payouts') }}" wire:navigate class="mt-2 text-sm">
                        {{ __('Payout history') }}
                    </flux:link>
                @endif
            </flux:card>
        </div>

        <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
            <flux:heading size="lg" class="mb-2 text-zinc-900 dark:text-zinc-100">{{ __('Quick links') }}</flux:heading>
            <div class="flex flex-wrap gap-3">
                <flux:button href="{{ route('doctor.profile') }}" wire:navigate variant="primary">{{ __('Profile') }}</flux:button>
                <flux:button href="{{ route('doctor.invoices') }}" wire:navigate variant="primary">{{ __('Invoices & shares') }}</flux:button>
                <flux:button href="{{ route('doctor.payouts') }}" wire:navigate variant="primary">{{ __('Payouts') }}</flux:button>
                <flux:button href="{{ route('doctor.appointments') }}" wire:navigate variant="primary">{{ __('Appointments & queue') }}</flux:button>
            </div>
        </flux:card>
    @else
        <flux:card class="p-8 text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Unable to load dashboard.') }}
        </flux:card>
    @endif
</div>
