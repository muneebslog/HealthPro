<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'doctors';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }
};
?>

@placeholder
    <div class="min-h-screen p-6" style="font-family:'JetBrains Mono','Fira Code',monospace;">
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <flux:skeleton class="h-10 w-24 rounded" />
                <flux:skeleton class="h-10 w-24 rounded" />
                <flux:skeleton class="h-10 w-20 rounded" />
            </div>
            <flux:skeleton.line class="w-full" />
            <flux:skeleton.line class="w-4/5" />
            <flux:skeleton.line class="w-3/4" />
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="min-h-screen " style="font-family:'JetBrains Mono','Fira Code',monospace;">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <style>
        .tab-bar { display:flex; gap:2px; border-bottom:1px solid #30363d; margin-bottom:24px; }
        .tab-btn {
            padding:10px 20px; font-size:12px; font-weight:600; letter-spacing:.08em;
            text-transform:uppercase; cursor:pointer; border:none; background:transparent;
            color:#484f58; border-bottom:2px solid transparent; margin-bottom:-1px;
            transition:all .15s; font-family:inherit;
        }
        .tab-btn:hover { color:#8b949e; }
        .tab-btn.active-doctors  { color:#00e5ff; border-bottom-color:#00e5ff; }
        .tab-btn.active-services { color:#bf7fff; border-bottom-color:#bf7fff; }
        .tab-btn.active-pricing  { color:#ffc940; border-bottom-color:#ffc940; }

        .tab-dot { display:inline-block; width:6px; height:6px; border-radius:50%; margin-right:7px; vertical-align:middle; }
        .dot-doctors  { background:#00e5ff; box-shadow:0 0 6px #00e5ff; }
        .dot-services { background:#bf7fff; box-shadow:0 0 6px #bf7fff; }
        .dot-pricing  { background:#ffc940; box-shadow:0 0 6px #ffc940; }
    </style>

    {{-- Tab Bar --}}
    <div class="tab-bar">
        <button wire:click="setTab('doctors')"
                class="tab-btn {{ $activeTab === 'doctors' ? 'active-doctors' : '' }}">
            <span class="tab-dot dot-doctors"></span>Doctors
        </button>
        <button wire:click="setTab('services')"
                class="tab-btn {{ $activeTab === 'services' ? 'active-services' : '' }}">
            <span class="tab-dot dot-services"></span>Services
        </button>
        <button wire:click="setTab('pricing')"
                class="tab-btn {{ $activeTab === 'pricing' ? 'active-pricing' : '' }}">
            <span class="tab-dot dot-pricing"></span>Pricing
        </button>
    </div>

    {{-- Tab Panels --}}
    <div>
        @if ($activeTab === 'doctors')
            <livewire:doccruds />
        @elseif ($activeTab === 'services')
            <livewire:servicecruds />
        @elseif ($activeTab === 'pricing')
            <livewire:servicepricecruds />
        @endif
    </div>
</div>