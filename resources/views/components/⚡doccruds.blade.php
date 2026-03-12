<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public bool $showModal = false;
    public bool $confirmingDelete = false;
    public ?int $deletingId = null;
    public ?int $editingId = null;

    public string $search = '';

    /** @var array<int, array{day: string, start_time: string, end_time: string}> */
    public array $scheduleRows = [];

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|string|max:255')]
    public string $specialization = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('boolean')]
    public bool $is_on_payroll = false;

    /** Payout interval in days for share-based doctors (e.g. 7, 15, 30). */
    #[Rule('nullable|integer|in:7,15,30')]
    public ?int $payout_duration = null;

    #[Rule('required|in:active,left,on_leave')]
    public string $status = 'active';

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->scheduleRows = [];
        $this->showModal = true;
    }

    public function openEdit(Doctor $doctor): void
    {
        $this->editingId = $doctor->id;
        $this->name = $doctor->name;
        $this->specialization = $doctor->specialization;
        $this->phone = $doctor->phone ?? '';
        $this->is_on_payroll = $doctor->is_on_payroll;
        $this->payout_duration = $doctor->payout_duration;
        $this->status = $doctor->status;
        $doctor->load('schedules');
        $this->scheduleRows = $doctor->schedules->map(fn (DoctorSchedule $s): array => [
            'day' => $s->day,
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_time' => substr((string) $s->end_time, 0, 5),
        ])->values()->all();
        $this->showModal = true;
    }

    public function addScheduleRow(): void
    {
        $this->scheduleRows[] = ['day' => 'monday', 'start_time' => '09:00', 'end_time' => '17:00'];
    }

    public function removeScheduleRow(int $index): void
    {
        array_splice($this->scheduleRows, $index, 1);
    }

    public function save(): void
    {
        $this->validate();

        foreach ($this->scheduleRows as $i => $row) {
            $this->validate([
                "scheduleRows.{$i}.day" => 'required|string|in:'.implode(',', self::DAYS),
                "scheduleRows.{$i}.start_time" => 'required|date_format:H:i',
                "scheduleRows.{$i}.end_time" => 'required|date_format:H:i|after:scheduleRows.'.$i.'.start_time',
            ], [], [
                "scheduleRows.{$i}.day" => 'day',
                "scheduleRows.{$i}.start_time" => 'start time',
                "scheduleRows.{$i}.end_time" => 'end time',
            ]);
        }

        $data = [
            'name' => $this->name,
            'specialization' => $this->specialization,
            'phone' => $this->phone ?: null,
            'is_on_payroll' => $this->is_on_payroll,
            'payout_duration' => $this->is_on_payroll ? null : $this->payout_duration,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            $doctor = Doctor::findOrFail($this->editingId);
            $doctor->update($data);
            $doctorId = $doctor->id;
            session()->flash('success', 'Doctor updated successfully.');
        } else {
            $doctor = Doctor::create($data);
            $doctorId = $doctor->id;
            session()->flash('success', 'Doctor created successfully.');
        }

        DoctorSchedule::where('doctor_id', $doctorId)->delete();
        foreach ($this->scheduleRows as $row) {
            DoctorSchedule::create([
                'doctor_id' => $doctorId,
                'day' => $row['day'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
            ]);
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
        Doctor::findOrFail($this->deletingId)->delete();
        $this->confirmingDelete = false;
        $this->deletingId = null;
        session()->flash('success', 'Doctor removed.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->specialization = '';
        $this->phone = '';
        $this->is_on_payroll = false;
        $this->payout_duration = null;
        $this->status = 'active';
        $this->scheduleRows = [];
        $this->resetValidation();
    }

    #[Computed]
    public function doctors()
    {
        return Doctor::query()
            ->with('schedules')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('specialization', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
    }

    public static function dayLabel(string $day): string
    {
        return ucfirst($day);
    }
};
?>

<div class="min-h-screen  font-mono "
     style="font-family: 'JetBrains Mono', 'Fira Code', monospace;">

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #00e5ff;
            --accent-dim: #00b8cc;
            --danger: #ff4466;
            --warn: #ffaa00;
            --success: #00ff88;
            --surface: #0d1117;
            --surface-2: #161b22;
            --surface-3: #21262d;
            --border: #30363d;
        }

        .hms-title { font-family: 'Syne', sans-serif; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 999px; font-size: 11px;
            font-weight: 600; letter-spacing: .06em; text-transform: uppercase;
        }
        .status-active  { background: rgba(0,255,136,.12); color: var(--success); border: 1px solid rgba(0,255,136,.25); }
        .status-left    { background: rgba(255,68,102,.12); color: var(--danger);  border: 1px solid rgba(255,68,102,.25); }
        .status-on_leave{ background: rgba(255,170,0,.12);  color: var(--warn);    border: 1px solid rgba(255,170,0,.25); }

        .hms-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 6px; font-size: 13px;
            font-weight: 600; cursor: pointer; transition: all .15s;
            border: 1px solid transparent; letter-spacing: .03em;
        }
        .hms-btn-primary { background: var(--accent); color: #000; }
        .hms-btn-primary:hover { background: #33ecff; transform: translateY(-1px); box-shadow: 0 4px 20px rgba(0,229,255,.3); }
        .hms-btn-ghost  { background: transparent; color: var(--accent); border-color: var(--accent); }
        .hms-btn-ghost:hover { background: rgba(0,229,255,.08); }
        .hms-btn-danger { background: transparent; color: var(--danger); border-color: var(--danger); }
        .hms-btn-danger:hover { background: rgba(255,68,102,.1); }
        .hms-btn-sm { padding: 4px 12px; font-size: 12px; }

        .hms-input {
            width: 100%; background: var(--surface); border: 1px solid var(--border);
            border-radius: 6px; padding: 9px 13px; color: #e6edf3; font-size: 13px;
            font-family: inherit; transition: border-color .15s;
            outline: none;
        }
        .hms-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,229,255,.1); }
        .hms-input::placeholder { color: #484f58; }

        .hms-label { font-size: 11px; font-weight: 600; color: #8b949e; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 5px; display: block; }

        .hms-table th { background: var(--surface); font-size: 11px; color: #8b949e; letter-spacing: .1em; text-transform: uppercase; padding: 10px 16px; border-bottom: 1px solid var(--border); }
        .hms-table td { padding: 13px 16px; border-bottom: 1px solid #1c2128; font-size: 13px; vertical-align: middle; }
        .hms-table tr:last-child td { border-bottom: none; }
        .hms-table tr:hover td { background: rgba(255,255,255,.02); }

        .hms-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.7); backdrop-filter: blur(4px); z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
        .hms-modal { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 500px; max-height: calc(100vh - 2rem); display: flex; flex-direction: column; box-shadow: 0 25px 80px rgba(0,0,0,.6); }
        .hms-modal-body { padding: 0 28px 28px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .hms-modal-footer { padding: 28px; padding-top: 0; flex-shrink: 0; }

        .hms-card { background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; }

        .toggle-wrap { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .toggle { width: 40px; height: 22px; border-radius: 999px; background: var(--surface-3); border: 1px solid var(--border); position: relative; transition: background .2s; flex-shrink: 0; }
        .toggle.on { background: var(--accent); border-color: var(--accent); }
        .toggle::after { content: ''; position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: transform .2s; }
        .toggle.on::after { transform: translateX(18px); }

        .payroll-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .payroll-yes { background: rgba(0,229,255,.1); color: var(--accent); }
        .payroll-no  { background: rgba(255,255,255,.05); color: #484f58; }

        @keyframes fadeIn { from { opacity:0; transform:scale(.97) } to { opacity:1; transform:scale(1) } }
        .hms-modal { animation: fadeIn .18s ease; }
    </style>

    {{-- Flash --}}
    @if (session()->has('success'))
        <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-lg text-sm"
             style="background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);color:var(--success);">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="hms-title text-2xl font-extrabold tracking-tight" style="color:var(--accent);">
                <span style="color:#8b949e;font-size:14px;font-family:monospace;display:block;margin-bottom:2px;">HMS / DOCTORS</span>
                Doctor Management
            </h1>
        </div>
        <button wire:click="openCreate" class="hms-btn hms-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Doctor
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-4 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:#484f58;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search doctors..."
               class="hms-input" style="padding-left:36px;">
    </div>

    {{-- Table --}}
    <div class="hms-card overflow-hidden">
        <table class="hms-table w-full">
            <thead>
                <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Name</th>
                    <th class="text-left">Specialization</th>
                    <th class="text-left">Phone</th>
                    <th class="text-left">Schedule</th>
                    <th class="text-left">Payroll</th>
                    <th class="text-left">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->doctors as $doctor)
                    <tr>
                        <td style="color:#484f58;">{{ $doctor->id }}</td>
                        <td>
                            <span class="font-semibold" style="color:#e6edf3;">{{ $doctor->name }}</span>
                        </td>
                        <td style="color:#8b949e;">{{ $doctor->specialization }}</td>
                        <td style="color:#8b949e;">{{ $doctor->phone ?? '—' }}</td>
                        <td style="color:#8b949e;font-size:12px;">
                            @forelse ($doctor->schedules as $s)
                                <span class="block">{{ ucfirst($s->day) }} {{ \Illuminate\Support\Str::substr($s->start_time, 0, 5) }}-{{ \Illuminate\Support\Str::substr($s->end_time, 0, 5) }}</span>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td style="color:#8b949e;font-size:12px;">
                            <span class="payroll-chip {{ $doctor->is_on_payroll ? 'payroll-yes' : 'payroll-no' }}">
                                {{ $doctor->is_on_payroll ? '✓ Payroll' : '✗ Share' }}
                            </span>
                            @if (! $doctor->is_on_payroll && $doctor->payout_duration)
                                <span class="block mt-0.5" style="color:#484f58;">{{ $doctor->payout_duration }} days</span>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge status-{{ $doctor->status }}">
                                {{ ucfirst($doctor->status) }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <button wire:click="openEdit({{ $doctor->id }})" class="hms-btn hms-btn-ghost hms-btn-sm">Edit</button>
                                <button wire:click="confirmDelete({{ $doctor->id }})" class="hms-btn hms-btn-danger hms-btn-sm">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-12" style="color:#484f58;">
                            No doctors found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $this->doctors->links() }}
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="hms-modal-backdrop" wire:click.self="$set('showModal', false)">
            <div class="hms-modal">
                <div class="flex items-center justify-between mb-0 px-6 pt-6 shrink-0">
                    <h2 class="hms-title text-lg font-bold" style="color:var(--accent);">
                        {{ $editingId ? 'Edit Doctor' : 'New Doctor' }}
                    </h2>
                    <button wire:click="$set('showModal', false)" style="color:#484f58;background:none;border:none;cursor:pointer;font-size:20px;">✕</button>
                </div>

                <div class="hms-modal-body">
                    <div class="space-y-4">
                    <div>
                        <label class="hms-label">Full Name</label>
                        <input wire:model="name" type="text" class="hms-input" placeholder="Dr. John Smith">
                        @error('name') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="hms-label">Specialization</label>
                        <input wire:model="specialization" type="text" class="hms-input" placeholder="Cardiology">
                        @error('specialization') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="hms-label">Phone <span style="color:#484f58;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input wire:model="phone" type="text" class="hms-input" placeholder="+1 555 000 0000">
                        @error('phone') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="hms-label">Status</label>
                        <select wire:model="status" class="hms-input">
                            <option value="active">Active</option>
                            <option value="left">Left</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                        @error('status') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="toggle-wrap" style="user-select:none;">
                            <div class="toggle {{ $is_on_payroll ? 'on' : '' }}" wire:click="$toggle('is_on_payroll')"></div>
                            <span class="hms-label" style="margin:0;">On Payroll</span>
                        </label>
                    </div>

                    @if (! $is_on_payroll)
                        <div>
                            <label class="hms-label">Payout duration <span style="color:#484f58;text-transform:none;">(share-based)</span></label>
                            <select wire:model="payout_duration" class="hms-input">
                                <option value="">— Select —</option>
                                <option value="7">Weekly (7 days)</option>
                                <option value="15">Every 15 days</option>
                                <option value="30">Monthly (30 days)</option>
                            </select>
                            @error('payout_duration') <p class="mt-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:0.5rem;">
                        <div class="flex items-center justify-between mb-3">
                            <label class="hms-label" style="margin:0;">Weekly schedule</label>
                            <button type="button" wire:click="addScheduleRow" class="hms-btn hms-btn-ghost hms-btn-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add slot
                            </button>
                        </div>
                        @foreach ($scheduleRows as $index => $row)
                            <div class="flex gap-2 items-end mb-2" wire:key="schedule-{{ $index }}">
                                <div class="flex-1 min-w-0">
                                    <label class="hms-label">Day</label>
                                    <select wire:model="scheduleRows.{{ $index }}.day" class="hms-input">
                                        @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d)
                                            <option value="{{ $d }}">{{ ucfirst($d) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="width:90px;">
                                    <label class="hms-label">Start</label>
                                    <input wire:model="scheduleRows.{{ $index }}.start_time" type="time" class="hms-input">
                                </div>
                                <div style="width:90px;">
                                    <label class="hms-label">End</label>
                                    <input wire:model="scheduleRows.{{ $index }}.end_time" type="time" class="hms-input">
                                </div>
                                <button type="button" wire:click="removeScheduleRow({{ $index }})" class="hms-btn hms-btn-danger hms-btn-sm shrink-0">✕</button>
                            </div>
                            @error("scheduleRows.{$index}.day") <p class="mt-0 mb-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                            @error("scheduleRows.{$index}.start_time") <p class="mt-0 mb-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                            @error("scheduleRows.{$index}.end_time") <p class="mt-0 mb-1 text-xs" style="color:var(--danger);">{{ $message }}</p> @enderror
                        @endforeach
                        @if (count($scheduleRows) === 0)
                            <p class="text-sm" style="color:#484f58;">No schedule slots. Click “Add slot” to set working hours.</p>
                        @endif
                    </div>
                    </div>
                </div>

                <div class="hms-modal-footer flex justify-end gap-3 pt-5 px-6 pb-6" style="border-top:1px solid var(--border);">
                    <button wire:click="$set('showModal', false)" class="hms-btn hms-btn-ghost">Cancel</button>
                    <button wire:click="save" class="hms-btn hms-btn-primary">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Create' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Confirm Delete Modal --}}
    @if ($confirmingDelete)
        <div class="hms-modal-backdrop" wire:click.self="$set('confirmingDelete', false)">
            <div class="hms-modal" style="max-width:380px;text-align:center;">
                <div class="mb-4 text-4xl">🗑️</div>
                <h2 class="hms-title font-bold text-lg mb-2" style="color:var(--danger);">Delete Doctor?</h2>
                <p class="text-sm mb-6" style="color:#8b949e;">This action cannot be undone. All related records may be affected.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('confirmingDelete', false)" class="hms-btn hms-btn-ghost">Cancel</button>
                    <button wire:click="delete" class="hms-btn hms-btn-danger">Yes, Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>