<?php

use App\Models\Doctor;
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
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-48" />
            <flux:card class="p-5">
                <flux:skeleton.line class="mb-4 w-32" />
                <div class="grid gap-4 sm:grid-cols-2"><flux:skeleton class="h-10 w-full rounded-lg" /><flux:skeleton class="h-10 w-full rounded-lg" /></div>
            </flux:card>
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="p-6 space-y-6">
    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
        {{ __('Profile') }}
    </flux:heading>

    @if ($this->doctor)
        <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
            <flux:heading size="lg" class="mb-4 text-zinc-900 dark:text-zinc-100">{{ $this->doctor->name }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label class="text-zinc-500 dark:text-zinc-400">{{ __('Specialization') }}</flux:label>
                    <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $this->doctor->specialization }}</flux:text>
                </flux:field>
                <flux:field>
                    <flux:label class="text-zinc-500 dark:text-zinc-400">{{ __('Phone') }}</flux:label>
                    <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $this->doctor->phone ?? '—' }}</flux:text>
                </flux:field>
                <flux:field>
                    <flux:label class="text-zinc-500 dark:text-zinc-400">{{ __('Payroll') }}</flux:label>
                    <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $this->doctor->is_on_payroll ? __('On payroll') : __('Share-based') }}</flux:text>
                </flux:field>
                @if (! $this->doctor->is_on_payroll && $this->doctor->payout_duration)
                    <flux:field>
                        <flux:label class="text-zinc-500 dark:text-zinc-400">{{ __('Payout period') }}</flux:label>
                        <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $this->doctor->payout_duration }} {{ __('days') }}</flux:text>
                    </flux:field>
                @endif
                <flux:field>
                    <flux:label class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:label>
                    <flux:text class="text-zinc-900 dark:text-zinc-100">{{ ucfirst($this->doctor->status) }}</flux:text>
                </flux:field>
            </div>
            <flux:text class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('To update your profile details, please contact the administrator.') }}</flux:text>
        </flux:card>

        <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <flux:heading size="lg" class="p-5 pb-2 text-zinc-900 dark:text-zinc-100">{{ __('Weekly schedule') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[400px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Day') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Start') }}</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('End') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @forelse ($this->doctor->schedules->sortBy('day') as $schedule)
                            <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100 capitalize">{{ $schedule->day }}</td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }}</td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ \Carbon\Carbon::parse($schedule->end_time)->format('g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No schedule set.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @else
        <flux:card class="p-8 text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Unable to load your profile.') }}
        </flux:card>
    @endif
</div>
