<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @var array<int, string>
     */
    public array $roleSelections = [];

    /**
     * @var array<int, string>
     */
    public array $doctorSelections = [];

    public function mount(): void
    {
        foreach ($this->users as $user) {
            $this->roleSelections[$user->id] = $user->role->value;
            $this->doctorSelections[$user->id] = (string) ($user->doctor?->id ?? '');
        }
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()->with('doctor')->orderBy('name')->get();
    }

    #[Computed]
    public function doctors(): Collection
    {
        return Doctor::query()->where('status', 'active')->orderBy('name')->get();
    }

    public function updatedRoleSelections(): void
    {
        foreach ($this->roleSelections as $userId => $roleValue) {
            $user = User::find($userId);
            if (! $user || $user->role->value === $roleValue) {
                continue;
            }
            $this->updateRole((int) $userId, $roleValue);
        }
    }

    public function updatedDoctorSelections(): void
    {
        foreach ($this->doctorSelections as $userId => $doctorId) {
            $role = $this->roleSelections[$userId] ?? null;
            if ($role !== UserRole::Doc->value) {
                continue;
            }
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $this->attachDoctor((int) $userId, $doctorId !== '' ? (int) $doctorId : null);
        }
    }

    public function updateRole(int $userId, string $role): void
    {
        $valid = collect(UserRole::cases())->contains(fn (UserRole $r) => $r->value === $role);
        if (! $valid) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if ($user->id === auth()->id() && $role !== UserRole::Admin->value) {
            $this->roleSelections[$userId] = UserRole::Admin->value;

            return;
        }

        if ($user->role === UserRole::Doc && $role !== UserRole::Doc->value) {
            Doctor::query()->where('user_id', $userId)->update(['user_id' => null]);
            $this->doctorSelections[$userId] = '';
        }

        $user->update(['role' => UserRole::from($role)]);
    }

    public function attachDoctor(int $userId, ?int $doctorId): void
    {
        $user = User::find($userId);
        if (! $user || $user->role !== UserRole::Doc) {
            return;
        }

        Doctor::query()->where('user_id', $userId)->update(['user_id' => null]);

        if ($doctorId !== null) {
            Doctor::query()->where('id', $doctorId)->update(['user_id' => $userId]);
        }
    }
};
?>

@placeholder
    <div class="p-6 space-y-6">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <flux:skeleton.line class="h-8 w-44" />
            <flux:card class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[640px]">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Role') }}</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('Doctor') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            @foreach (range(1, 6) as $i)
                                <tr>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-32" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-48" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-20" /></td>
                                    <td class="px-5 py-4"><flux:skeleton.line class="w-24" /></td>
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-50">
            {{ __('User accounts') }}
        </flux:heading>
    </div>

    <flux:card class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[640px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80">
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Name') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Role') }}
                        </th>
                        <th class="px-5 py-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Doctor') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $user->name }}
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $user->email }}
                            </td>
                            <td class="px-5 py-4">
                                @if ($user->id === auth()->id())
                                    <flux:select
                                        wire:model.live="roleSelections.{{ $user->id }}"
                                        disabled
                                        class="min-w-28"
                                    >
                                        @foreach (UserRole::cases() as $roleOption)
                                            <flux:select.option value="{{ $roleOption->value }}">{{ ucfirst($roleOption->value) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('You cannot change your own role.') }}</p>
                                @else
                                    <flux:select
                                        wire:model.live="roleSelections.{{ $user->id }}"
                                        class="min-w-28"
                                    >
                                        @foreach (UserRole::cases() as $roleOption)
                                            <flux:select.option value="{{ $roleOption->value }}">{{ ucfirst($roleOption->value) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if (($roleSelections[$user->id] ?? $user->role->value) === 'doc')
                                    <flux:select
                                        wire:model.live="doctorSelections.{{ $user->id }}"
                                        class="min-w-40"
                                    >
                                        <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                                        @foreach ($this->doctors as $doctor)
                                            <flux:select.option value="{{ $doctor->id }}">{{ $doctor->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No users.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
