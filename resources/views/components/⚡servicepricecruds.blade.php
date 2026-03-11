<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ServicePrice;
use App\Models\Service;
use App\Models\Doctor;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public bool $showModal = false;
    public bool $confirmingDelete = false;
    public ?int $deletingId = null;
    public ?int $editingId = null;

    public string $search = '';
    public string $filterService = '';
    public string $filterDoctor = '';

    #[Rule('required|exists:services,id')]
    public ?int $service_id = null;

    #[Rule('nullable|exists:doctors,id')]
    public ?int $doctor_id = null;

    #[Rule('required|integer|min:0')]
    public string $price = '';

    #[Rule('nullable|integer|min:0')]
    public string $doctor_share = '';

    #[Rule('required|integer|min:0')]
    public string $hospital_share = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterService(): void
    {
        $this->resetPage();
    }
    public function updatingFilterDoctor(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(ServicePrice $servicePrice): void
    {
        $this->editingId = $servicePrice->id;
        $this->service_id = $servicePrice->service_id;
        $this->doctor_id = $servicePrice->doctor_id;
        $this->price = (string) $servicePrice->price;
        $this->doctor_share = $servicePrice->doctor_share !== null ? (string) $servicePrice->doctor_share : '';
        $this->hospital_share = (string) $servicePrice->hospital_share;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'service_id' => $this->service_id,
            'doctor_id' => $this->doctor_id ?: null,
            'price' => (int) $this->price,
            'doctor_share' => $this->doctor_share !== '' ? (int) $this->doctor_share : null,
            'hospital_share' => (int) $this->hospital_share,
        ];


        if ($this->editingId) {
            ServicePrice::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Service price updated.');
        } else {

            ServicePrice::create($data);
            session()->flash('success', 'Service price created.');
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
        ServicePrice::findOrFail($this->deletingId)->delete();
        $this->confirmingDelete = false;
        $this->deletingId = null;
        session()->flash('success', 'Price entry removed.');
    }

    public function updatedPrice(): void
    {
        // Auto-calculate hospital share if price and doctor_share are set
        if ($this->price !== '' && $this->doctor_share !== '') {
            $this->hospital_share = (string) max(0, (int) $this->price - (int) $this->doctor_share);
        }
    }

    public function updatedDoctorShare(): void
    {
        if ($this->price !== '' && $this->doctor_share !== '') {
            $this->hospital_share = (string) max(0, (int) $this->price - (int) $this->doctor_share);
        }
    }

    public function resetForm(): void
    {
        $this->service_id = null;
        $this->doctor_id = null;
        $this->price = '';
        $this->doctor_share = '';
        $this->hospital_share = '';
        $this->resetValidation();
    }

    #[Computed]
    public function prices()
    {
        return ServicePrice::query()
            ->with(['service', 'doctor'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->whereHas('service', fn($q2) =>
                        $q2->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('doctor', fn($q2) =>
                            $q2->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->filterService, fn($q) =>
                $q->where('service_id', $this->filterService))
            ->when($this->filterDoctor, fn($q) =>
                $q->where('doctor_id', $this->filterDoctor))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function services()
    {
        return Service::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function doctors()
    {
        return Doctor::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

};
?>

<div class="min-h-screen bg-slate-950 text-slate-100 p-6"
    style="font-family: 'JetBrains Mono', 'Fira Code', monospace;">

    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --accent: #00e5ff;
            --gold: #ffc940;
            --danger: #ff4466;
            --success: #00ff88;
            --surface: #0d1117;
            --surface-2: #161b22;
            --surface-3: #21262d;
            --border: #30363d;
        }

        .hms-title {
            font-family: 'Syne', sans-serif;
        }

        .hms-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            border: 1px solid transparent;
            letter-spacing: .03em;
        }

        .hms-btn-primary {
            background: var(--gold);
            color: #000;
        }

        .hms-btn-primary:hover {
            background: #ffd666;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(255, 201, 64, .3);
        }

        .hms-btn-ghost {
            background: transparent;
            color: var(--gold);
            border-color: var(--gold);
        }

        .hms-btn-ghost:hover {
            background: rgba(255, 201, 64, .08);
        }

        .hms-btn-danger {
            background: transparent;
            color: var(--danger);
            border-color: var(--danger);
        }

        .hms-btn-danger:hover {
            background: rgba(255, 68, 102, .1);
        }

        .hms-btn-sm {
            padding: 4px 12px;
            font-size: 12px;
        }

        .hms-input {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 9px 13px;
            color: #e6edf3;
            font-size: 13px;
            font-family: inherit;
            transition: border-color .15s;
            outline: none;
        }

        .hms-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(255, 201, 64, .1);
        }

        .hms-input::placeholder {
            color: #484f58;
        }

        .hms-label {
            font-size: 11px;
            font-weight: 600;
            color: #8b949e;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .hms-table th {
            background: var(--surface);
            font-size: 11px;
            color: #8b949e;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
        }

        .hms-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #1c2128;
            font-size: 13px;
            vertical-align: middle;
        }

        .hms-table tr:last-child td {
            border-bottom: none;
        }

        .hms-table tr:hover td {
            background: rgba(255, 255, 255, .02);
        }

        .hms-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            backdrop-filter: blur(4px);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hms-modal {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            padding: 28px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, .6);
            animation: fadeIn .18s ease;
        }

        .hms-card {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(.97)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        .amount-cell {
            font-variant-numeric: tabular-nums;
        }

        .currency {
            color: #484f58;
            font-size: 11px;
            margin-right: 2px;
        }

        .share-bar {
            height: 6px;
            border-radius: 999px;
            background: var(--surface-3);
            overflow: hidden;
            margin-top: 4px;
        }

        .share-bar-fill {
            height: 100%;
            border-radius: 999px;
        }

        .price-card {
            border-radius: 8px;
            padding: 16px;
            background: var(--surface);
            border: 1px solid var(--border);
        }

        .price-card-label {
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #484f58;
            margin-bottom: 4px;
        }

        .price-card-value {
            font-size: 20px;
            font-weight: 700;
        }

        .hint {
            font-size: 11px;
            color: #484f58;
            margin-top: 3px;
        }
    </style>

    @if (session()->has('success'))
        <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-lg text-sm"
            style="background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);color:var(--success);">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clip-rule="evenodd" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="hms-title text-2xl font-extrabold tracking-tight" style="color:var(--gold);">
                <span style="color:#8b949e;font-size:14px;font-family:monospace;display:block;margin-bottom:2px;">HMS /
                    PRICING</span>
                Service Pricing
            </h1>
        </div>
        <button wire:click="openCreate" class="hms-btn hms-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Price
        </button>
    </div>

    {{-- Filters --}}
    <div class="grid gap-3 mb-4" style="grid-template-columns:1fr 200px 200px;">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:#484f58;" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search..." class="hms-input"
                style="padding-left:36px;">
        </div>
        <select wire:model.live="filterService" class="hms-input">
            <option value="">All Services</option>
            @foreach ($this->services as $svc)
                <option value="{{ $svc->id }}">{{ $svc->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterDoctor" class="hms-input">
            <option value="">All Doctors</option>
            @foreach ($this->doctors as $doc)
                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Table --}}
    <div class="hms-card overflow-hidden">
        <table class="hms-table w-full">
            <thead>
                <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Service</th>
                    <th class="text-left">Doctor</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Dr. Share</th>
                    <th class="text-right">Hosp. Share</th>
                    <th class="text-left" style="width:120px;">Split</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->prices as $sp)
                    @php
                        $drPct = $sp->price > 0 && $sp->doctor_share !== null
                            ? round($sp->doctor_share / $sp->price * 100) : 0;
                        $hPct = $sp->price > 0
                            ? round($sp->hospital_share / $sp->price * 100) : 0;
                    @endphp
                    <tr>
                        <td style="color:#484f58;">{{ $sp->id }}</td>
                        <td>
                            <span class="font-semibold" style="color:#e6edf3;">{{ $sp->service->name }}</span>
                        </td>
                        <td style="color:#8b949e;">
                            {{ $sp->doctor?->name ?? '—' }}
                        </td>
                        <td class="text-right amount-cell">
                            <span class="currency">PKR</span>
                            <span class="font-semibold" style="color:var(--gold);">{{ number_format($sp->price) }}</span>
                        </td>
                        <td class="text-right amount-cell" style="color:#8b949e;">
                            {{ $sp->doctor_share !== null ? number_format($sp->doctor_share) : '—' }}
                        </td>
                        <td class="text-right amount-cell" style="color:#8b949e;">
                            {{ number_format($sp->hospital_share) }}
                        </td>
                        <td>
                            @if ($sp->price > 0 && $sp->doctor_share !== null)
                                <div class="share-bar">
                                    <div class="share-bar-fill" style="width:{{ $drPct }}%;background:var(--accent);"></div>
                                </div>
                                <div class="flex justify-between mt-1" style="font-size:10px;color:#484f58;">
                                    <span>Dr {{ $drPct }}%</span>
                                    <span>H {{ $hPct }}%</span>
                                </div>
                            @else
                                <span style="color:#484f58;font-size:12px;">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <button wire:click="openEdit({{ $sp->id }})"
                                    class="hms-btn hms-btn-ghost hms-btn-sm">Edit</button>
                                <button wire:click="confirmDelete({{ $sp->id }})"
                                    class="hms-btn hms-btn-danger hms-btn-sm">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-12" style="color:#484f58;">No pricing records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->prices->links() }}</div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="hms-modal-backdrop" wire:click.self="$set('showModal', false)">
            <div class="hms-modal">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="hms-title text-lg font-bold" style="color:var(--gold);">
                        {{ $editingId ? 'Edit Price Entry' : 'New Price Entry' }}
                    </h2>
                    <button wire:click="$set('showModal', false)"
                        style="color:#484f58;background:none;border:none;cursor:pointer;font-size:20px;">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="hms-label">Service <span style="color:var(--danger);">*</span></label>
                            <select wire:model="service_id" class="hms-input">
                                <option value="">— Select Service —</option>
                                @foreach ($this->services as $svc)
                                    <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                                @endforeach
                            </select>
                            @error('service_id') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-2">
                            <label class="hms-label">Doctor <span
                                    style="color:#484f58;text-transform:none;letter-spacing:0;">(optional)</span></label>
                            <select wire:model="doctor_id" class="hms-input">
                                <option value="">— No Doctor / General —</option>
                                @foreach ($this->doctors as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                                @endforeach
                            </select>
                            @error('doctor_id') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Price Cards --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div class="price-card">
                            <div class="price-card-label">Total Price <span style="color:var(--danger);">*</span></div>
                            <input wire:model.live="price" type="number" min="0" class="hms-input"
                                style="background:transparent;border:none;border-bottom:1px solid var(--border);border-radius:0;padding:4px 0;font-size:18px;font-weight:700;color:var(--gold);"
                                placeholder="0">
                            @error('price') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="price-card">
                            <div class="price-card-label">Doctor Share</div>
                            <input wire:model.live="doctor_share" type="number" min="0" class="hms-input"
                                style="background:transparent;border:none;border-bottom:1px solid var(--border);border-radius:0;padding:4px 0;font-size:18px;font-weight:700;color:var(--accent);"
                                placeholder="0">
                            @error('doctor_share') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="price-card">
                            <div class="price-card-label">Hospital Share <span style="color:var(--danger);">*</span></div>
                            <input wire:model="hospital_share" type="number" min="0" class="hms-input"
                                style="background:transparent;border:none;border-bottom:1px solid var(--border);border-radius:0;padding:4px 0;font-size:18px;font-weight:700;color:#e6edf3;"
                                placeholder="0">
                            @error('hospital_share') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="hint">💡 Doctor share auto-calculates hospital share when both price and doctor share are
                        entered.</p>

                    {{-- Live split bar --}}
                    @if ($price && $doctor_share)
                        @php $pct = $price > 0 ? round(intval($doctor_share) / intval($price) * 100) : 0; @endphp
                        <div>
                            <div class="share-bar" style="height:8px;">
                                <div class="share-bar-fill"
                                    style="width:{{ $pct }}%;background:linear-gradient(90deg,var(--accent),var(--gold));">
                                </div>
                            </div>
                            <div class="flex justify-between mt-1" style="font-size:11px;color:#8b949e;">
                                <span>Doctor: {{ $pct }}%</span>
                                <span>Hospital: {{ 100 - $pct }}%</span>
                            </div>
                        </div>
                    @endif
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
                <h2 class="hms-title font-bold text-lg mb-2" style="color:var(--danger);">Delete Price Entry?</h2>
                <p class="text-sm mb-6" style="color:#8b949e;">This may affect existing invoices referencing this price.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('confirmingDelete', false)" class="hms-btn hms-btn-ghost">Cancel</button>
                    <button wire:click="delete" class="hms-btn hms-btn-danger">Yes, Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>