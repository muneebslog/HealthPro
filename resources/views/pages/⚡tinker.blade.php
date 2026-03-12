<?php

use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    /** @var array<int, object>|null */
    public ?array $result = null;

    public ?string $error = null;

    public ?int $rowCount = null;

    public function runQuery(): void
    {
        $this->result = null;
        $this->error = null;
        $this->rowCount = null;

        $sql = trim($this->query);
        if ($sql === '') {
            $this->addError('query', __('Enter a query to run.'));

            return;
        }

        try {
            $isSelect = preg_match('/^\s*select\b/i', $sql) === 1;
            if ($isSelect) {
                $this->result = DB::select($sql);
                $this->rowCount = count($this->result);
            } else {
                $this->rowCount = DB::affectingStatement($sql);
                $this->result = $this->rowCount !== null ? [['affected' => $this->rowCount]] : null;
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center gap-2">
        <flux:heading size="xl">{{ __('Tinker') }}</flux:heading>
        <flux:badge color="zinc" size="sm">{{ __('Testing') }}</flux:badge>
    </div>

    <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
        {{ __('For development and testing only. Runs raw SQL against the default database connection.') }}
    </flux:callout>

    <flux:card class="p-5">
        <flux:heading size="lg" class="mb-4">{{ __('Run query') }}</flux:heading>
        <div class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Query') }}</flux:label>
                <flux:textarea
                    wire:model="query"
                    placeholder="SELECT * FROM users LIMIT 5"
                    rows="6"
                    class="font-mono text-sm"
                />
                <flux:error name="query" />
            </flux:field>
            <flux:button variant="primary" wire:click="runQuery" wire:loading.attr="disabled">
                <flux:icon.bolt class="size-4" wire:loading.remove />
                <flux:icon.loading class="size-4" wire:loading />
                {{ __('Run query') }}
            </flux:button>
        </div>
    </flux:card>

    @if ($error !== null)
        <flux:card class="p-5 border-red-200 dark:border-red-800">
            <flux:callout variant="danger" icon="x-circle">
                <pre class="whitespace-pre-wrap text-sm font-mono">{{ $error }}</pre>
            </flux:callout>
        </flux:card>
    @endif

    @if ($result !== null && $error === null)
        <flux:card class="p-5 overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Result') }}</flux:heading>
                @if ($rowCount !== null)
                    <flux:badge color="zinc">{{ $rowCount }} {{ __('row(s)') }}</flux:badge>
                @endif
            </div>
            @if (empty($result))
                <flux:subheading>{{ __('No rows returned.') }}</flux:subheading>
            @else
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-100 dark:bg-zinc-800">
                            <tr>
                                @foreach ((array) $result[0] as $key => $_)
                                    <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-400">{{ $key }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result as $row)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    @foreach ((array) $row as $cell)
                                        <td class="px-4 py-2 font-mono">{{ $cell === null ? 'NULL' : e((string) $cell) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif
</div>
