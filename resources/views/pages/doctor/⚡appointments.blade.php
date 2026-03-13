<?php

use App\Models\Doctor;
use App\Models\Queue;
use App\Models\QueueToken;
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
    public function currentQueue(): ?Queue
    {
        $doctor = $this->doctor;
        if (! $doctor) {
            return null;
        }

        return Queue::query()
            ->active()
            ->where('doctor_id', $doctor->id)
            ->where('service_id', Queue::APPOINTMENT_SERVICE_ID)
            ->with(['queueTokens.patient'])
            ->first();
    }

    #[Computed]
    public function tokensSummary(): array
    {
        $queue = $this->currentQueue;
        if (! $queue) {
            return ['total' => 0, 'reserved' => 0, 'arrived' => 0, 'called' => 0, 'completed' => 0, 'available' => 0];
        }

        $tokens = $queue->queueTokens;
        return [
            'total' => $tokens->count(),
            'reserved' => $tokens->where('status', 'reserved')->count(),
            'arrived' => $tokens->where('status', 'arrived')->count(),
            'called' => $tokens->where('status', 'called')->count(),
            'completed' => $tokens->where('status', 'completed')->count(),
            'available' => $tokens->where('status', 'available')->count(),
        ];
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <flux:card class="p-5">
                <div class="grid gap-4 sm:grid-cols-4"><flux:skeleton class="h-16 w-full rounded-lg" /><flux:skeleton class="h-16 w-full rounded-lg" /><flux:skeleton class="h-16 w-full rounded-lg" /><flux:skeleton class="h-16 w-full rounded-lg" /></div>
            </flux:card>
            <flux:card class="p-5 overflow-hidden">
                <flux:skeleton.line class="mb-4 w-32" />
                <div class="space-y-2">@foreach (range(1, 8) as $i)<flux:skeleton.line class="h-12 w-full" />@endforeach</div>
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6" wire:poll.10s>
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Appointments & queue') }}
    </flux:heading>

    @if ($this->doctor)
        @if ($this->currentQueue)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total tokens') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->tokensSummary['total'] }}</flux:heading>
                </flux:card>
                <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->tokensSummary['reserved'] + $this->tokensSummary['available'] }}</flux:heading>
                </flux:card>
                <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Arrived / Called') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->tokensSummary['arrived'] + $this->tokensSummary['called'] }}</flux:heading>
                </flux:card>
                <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->tokensSummary['completed'] }}</flux:heading>
                </flux:card>
            </div>

            <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
                <flux:heading size="lg" class="p-5 pb-2 text-zinc-900 dark:text-zinc-100">{{ __('Current queue') }}</flux:heading>
                <flux:text class="px-5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Reception controls calling and completion. This view updates automatically.') }}</flux:text>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[520px]">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Token') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Patient') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Reserved at') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Called / Completed') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @forelse ($this->currentQueue->queueTokens->sortBy('token_number') as $token)
                                <tr wire:key="token-{{ $token->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-5 py-4 text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">#{{ $token->token_number }}</td>
                                    <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $token->patient?->name ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        @php
                                            $statusClasses = [
                                                'reserved' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 border border-amber-200/60 dark:border-amber-700/50',
                                                'available' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-600 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-600',
                                                'arrived' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200 border border-sky-200/60 dark:border-sky-700/50',
                                                'called' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200 border border-violet-200/60 dark:border-violet-700/50',
                                                'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 border border-emerald-200/60 dark:border-emerald-700/50',
                                            ];
                                            $classes = $statusClasses[$token->status] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-600 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-600';
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $classes }}">
                                            {{ ucfirst($token->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $token->reserved_at?->format('h:i A') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        @if ($token->called_at)
                                            {{ $token->called_at->format('h:i A') }}
                                        @elseif ($token->completed_at)
                                            {{ $token->completed_at->format('h:i A') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No tokens in this queue.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @else
            <flux:card class="p-8 text-center border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900">
                <flux:text class="text-zinc-600 dark:text-zinc-400">{{ __('No active appointment queue for you today. The queue is created by reception when managing appointments.') }}</flux:text>
            </flux:card>
        @endif
    @else
        <flux:card class="p-8 text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Unable to load appointments.') }}
        </flux:card>
    @endif
</div>
