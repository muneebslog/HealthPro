<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Service;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public bool $showModal = false;
    public bool $confirmingDelete = false;
    public ?int $deletingId = null;
    public ?int $editingId = null;

    public string $search = '';

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('boolean')]
    public bool $is_standalone = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(Service $service): void
    {
        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->is_standalone = $service->is_standalone;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'is_standalone' => $this->is_standalone,
        ];

        if ($this->editingId) {
            Service::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Service updated successfully.');
        } else {
            Service::create($data);
            session()->flash('success', 'Service created successfully.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        Service::findOrFail($this->deletingId)->delete();
        $this->confirmingDelete = false;
        $this->deletingId = null;
        session()->flash('success', 'Service removed.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->is_standalone = false;
        $this->resetValidation();
    }

     #[Computed]
    public function services()
    {
        return  Service::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->withCount(['servicePrices', 'visitServices'])
                ->latest()
                ->paginate(10);
    }
};
?>

<div class="min-h-screen "
     style="font-family: 'JetBrains Mono', 'Fira Code', monospace;">

    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #00e5ff;
            --danger: #ff4466;
            --success: #00ff88;
            --purple: #bf7fff;
            --surface: #0d1117;
            --surface-2: #161b22;
            --surface-3: #21262d;
            --border: #30363d;
        }
        .hms-title { font-family: 'Syne', sans-serif; }
        .hms-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;border:1px solid transparent;letter-spacing:.03em; }
        .hms-btn-primary { background:var(--accent);color:#000; }
        .hms-btn-primary:hover { background:#33ecff;transform:translateY(-1px);box-shadow:0 4px 20px rgba(0,229,255,.3); }
        .hms-btn-ghost { background:transparent;color:var(--accent);border-color:var(--accent); }
        .hms-btn-ghost:hover { background:rgba(0,229,255,.08); }
        .hms-btn-danger { background:transparent;color:var(--danger);border-color:var(--danger); }
        .hms-btn-danger:hover { background:rgba(255,68,102,.1); }
        .hms-btn-sm { padding:4px 12px;font-size:12px; }
        .hms-input { width:100%;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:9px 13px;color:#e6edf3;font-size:13px;font-family:inherit;transition:border-color .15s;outline:none; }
        .hms-input:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,229,255,.1); }
        .hms-input::placeholder { color:#484f58; }
        .hms-label { font-size:11px;font-weight:600;color:#8b949e;letter-spacing:.08em;text-transform:uppercase;margin-bottom:5px;display:block; }
        .hms-table th { background:var(--surface);font-size:11px;color:#8b949e;letter-spacing:.1em;text-transform:uppercase;padding:10px 16px;border-bottom:1px solid var(--border); }
        .hms-table td { padding:13px 16px;border-bottom:1px solid #1c2128;font-size:13px;vertical-align:middle; }
        .hms-table tr:last-child td { border-bottom:none; }
        .hms-table tr:hover td { background:rgba(255,255,255,.02); }
        .hms-modal-backdrop { position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:50;display:flex;align-items:center;justify-content:center; }
        .hms-modal { background:var(--surface-2);border:1px solid var(--border);border-radius:12px;width:100%;max-width:480px;padding:28px;box-shadow:0 25px 80px rgba(0,0,0,.6);animation:fadeIn .18s ease; }
        .hms-card { background:var(--surface-2);border:1px solid var(--border);border-radius:10px; }
        .toggle-wrap { display:flex;align-items:center;gap:10px;cursor:pointer; }
        .toggle { width:40px;height:22px;border-radius:999px;background:var(--surface-3);border:1px solid var(--border);position:relative;transition:background .2s;flex-shrink:0; }
        .toggle.on { background:var(--purple);border-color:var(--purple); }
        .toggle::after { content:'';position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s; }
        .toggle.on::after { transform:translateX(18px); }
        .standalone-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:.06em; }
        .standalone-yes { background:rgba(191,127,255,.12);color:var(--purple);border:1px solid rgba(191,127,255,.25); }
        .standalone-no  { background:rgba(255,255,255,.05);color:#484f58;border:1px solid #30363d; }
        .count-pill { display:inline-block;background:rgba(0,229,255,.1);color:var(--accent);border-radius:4px;padding:1px 7px;font-size:11px;font-weight:600; }
        @keyframes fadeIn { from{opacity:0;transform:scale(.97)} to{opacity:1;transform:scale(1)} }
    </style>

    @if (session()->has('success'))
        <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-lg text-sm"
             style="background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);color:var(--success);">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="hms-title text-2xl font-extrabold tracking-tight" style="color:var(--purple);">
                <span style="color:#8b949e;font-size:14px;font-family:monospace;display:block;margin-bottom:2px;">HMS / SERVICES</span>
                Service Catalogue
            </h1>
        </div>
        <button wire:click="openCreate" class="hms-btn hms-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Service
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-4 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:#484f58;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search services..."
               class="hms-input" style="padding-left:36px;">
    </div>

    {{-- Table --}}
    <div class="hms-card overflow-hidden">
        <table class="hms-table w-full">
            <thead>
                <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Service Name</th>
                    <th class="text-left">Type</th>
                    <th class="text-left">Prices</th>
                    <th class="text-left">Visits</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->services as $service)
                    <tr>
                        <td style="color:#484f58;">{{ $service->id }}</td>
                        <td>
                            <span class="font-semibold" style="color:#e6edf3;">{{ $service->name }}</span>
                        </td>
                        <td>
                            <span class="standalone-badge {{ $service->is_standalone ? 'standalone-yes' : 'standalone-no' }}">
                                {{ $service->is_standalone ? '⬡ Standalone' : '⬡ Bundled' }}
                            </span>
                        </td>
                        <td><span class="count-pill">{{ $service->service_prices_count }}</span></td>
                        <td><span class="count-pill">{{ $service->visit_services_count }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <button wire:click="openEdit({{ $service->id }})" class="hms-btn hms-btn-ghost hms-btn-sm">Edit</button>
                                <button wire:click="confirmDelete({{ $service->id }})" class="hms-btn hms-btn-danger hms-btn-sm">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-12" style="color:#484f58;">No services found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->services->links() }}</div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="hms-modal-backdrop" wire:click.self="$set('showModal', false)">
            <div class="hms-modal">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="hms-title text-lg font-bold" style="color:var(--purple);">
                        {{ $editingId ? 'Edit Service' : 'New Service' }}
                    </h2>
                    <button wire:click="$set('showModal', false)" style="color:#484f58;background:none;border:none;cursor:pointer;font-size:20px;">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="hms-label">Service Name</label>
                        <input wire:model="name" type="text" class="hms-input" placeholder="e.g. Blood Test, ECG, X-Ray">
                        @error('name') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-4 rounded-lg" style="background:var(--surface);border:1px solid var(--border);">
                        <label class="toggle-wrap" style="user-select:none;">
                            <div class="toggle {{ $is_standalone ? 'on' : '' }}" wire:click="$toggle('is_standalone')"></div>
                            <div>
                                <span class="hms-label" style="margin:0;">Standalone Service</span>
                                <p class="text-xs mt-1" style="color:#484f58;">Can be prescribed independently without a bundle</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-5" style="border-top:1px solid var(--border);">
                    <button wire:click="$set('showModal', false)" class="hms-btn hms-btn-ghost">Cancel</button>
                    <button wire:click="save" class="hms-btn hms-btn-primary">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Create' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Confirm Delete --}}
    @if ($confirmingDelete)
        <div class="hms-modal-backdrop" wire:click.self="$set('confirmingDelete', false)">
            <div class="hms-modal" style="max-width:380px;text-align:center;">
                <div class="mb-4 text-4xl">🗑️</div>
                <h2 class="hms-title font-bold text-lg mb-2" style="color:var(--danger);">Delete Service?</h2>
                <p class="text-sm mb-6" style="color:#8b949e;">This will also remove all related prices and visit records.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('confirmingDelete', false)" class="hms-btn hms-btn-ghost">Cancel</button>
                    <button wire:click="delete" class="hms-btn hms-btn-danger">Yes, Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>