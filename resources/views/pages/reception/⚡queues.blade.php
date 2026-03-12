<?php

use App\Models\Queue;
use App\Models\QueueToken;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showOlder = false;

    public ?int $selectedQueueId = null;

    public function selectQueue(int $id): void
    {
        $this->selectedQueueId = $this->selectedQueueId === $id ? null : $id;
    }

    #[Computed]
    public function queues()
    {
        if (! $this->showOlder) {
            return Queue::query()
                ->with(['service', 'doctor'])
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->latest('started_at')
                ->get();
        }

        return Queue::query()
            ->with(['service', 'doctor'])
            ->where(function ($q): void {
                $q->where('status', 'discontinued')
                    ->orWhereNotNull('ended_at');
            })
            ->latest('ended_at')
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function selectedQueueTokens()
    {
        if ($this->selectedQueueId === null) {
            return collect();
        }

        return QueueToken::query()
            ->where('queue_id', $this->selectedQueueId)
            ->with('patient')
            ->orderBy('token_number')
            ->get();
    }

    #[Computed]
    public function selectedQueue()
    {
        if ($this->selectedQueueId === null) {
            return null;
        }

        return Queue::with(['service', 'doctor'])->find($this->selectedQueueId);
    }
};
?>

<div class="p-6 space-y-6" @if(!$showOlder) wire:poll.5s @endif>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
            {{ __('Queues') }}
        </flux:heading>
    </div>

    {{-- Current / Older toggle --}}
    <flux:card class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm">
        <div class="flex flex-wrap gap-2">
            <flux:button
                variant="{{ !$showOlder ? 'filled' : 'ghost' }}"
                wire:click="$set('showOlder', false)"
            >
                {{ __('Current queues') }}
            </flux:button>
            <flux:button
                variant="{{ $showOlder ? 'filled' : 'ghost' }}"
                wire:click="$set('showOlder', true)"
            >
                {{ __('Older queues') }}
            </flux:button>
        </div>
    </flux:card>

    {{-- Queues table --}}
    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Service') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Doctor') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Queue type') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Current token') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Status') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Started at') }}
                        </th>
                        @if ($showOlder)
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Ended at') }}
                            </th>
                        @endif
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right w-24">
                            {{ __('Tokens') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->queues as $queue)
                        <tr
                            wire:key="queue-{{ $queue->id }}"
                            wire:click="selectQueue({{ $queue->id }})"
                            role="button"
                            tabindex="0"
                            class="cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $selectedQueueId === $queue->id ? 'bg-sky-50 dark:bg-sky-900/20 ring-inset ring-1 ring-sky-200 dark:ring-sky-700' : '' }}"
                        >
                            <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $queue->service?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $queue->doctor?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 capitalize">
                                {{ $queue->queue_type }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $queue->current_token }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $queue->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 border border-emerald-200/60 dark:border-emerald-700/50' : 'bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200 border border-zinc-200/60 dark:border-zinc-600/50' }}"
                                >
                                    {{ $queue->status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                {{ $queue->started_at?->format('M j, Y') ?? '—' }}
                                @if ($queue->started_at)
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $queue->started_at->format('h:i A') }}</span>
                                @endif
                            </td>
                            @if ($showOlder)
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $queue->ended_at?->format('M j, Y') ?? '—' }}
                                    @if ($queue->ended_at)
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $queue->ended_at->format('h:i A') }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-5 py-4 text-right">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('View tokens') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showOlder ? 8 : 7 }}" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ $showOlder ? __('No older queues found.') : __('No current queues.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Tokens list for selected queue --}}
    @if ($selectedQueueId && $this->selectedQueue)
        <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
            <div class="flex items-center justify-between p-5 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-50">
                    {{ __('Tokens for') }} {{ $this->selectedQueue->service?->name ?? '—' }} — {{ $this->selectedQueue->doctor?->name ?? '—' }}
                </flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="$set('selectedQueueId', null)">
                    {{ __('Close') }}
                </flux:button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[640px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Token #') }}
                            </th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Patient') }}
                            </th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Status') }}
                            </th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Reserved at') }}
                            </th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Called at') }}
                            </th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Completed at') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @forelse ($this->selectedQueueTokens as $token)
                            <tr wire:key="token-{{ $token->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $token->token_number }}
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $token->patient?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                            @switch($token->status)
                                                @case('reserved') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 @break
                                                @case('waiting') bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200 @break
                                                @case('called') bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200 @break
                                                @case('completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 @break
                                                @case('skipped') bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300 @break
                                                @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200 @break
                                                @default bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200
                                            @endswitch
                                        "
                                    >
                                        {{ $token->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $token->reserved_at?->format('M j, Y h:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $token->called_at?->format('M j, Y h:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                    {{ $token->completed_at?->format('M j, Y h:i A') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No tokens in this queue.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
