<?php

use App\Actions\PrintReceipt;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\Visit;
use App\Models\VisitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Rule;

new class extends Component
{
    private static function appointmentServiceId(): int
    {
        return 1;
    }

    private static function slotCount(): int
    {
        return 50;
    }

    private static function minutesPerPatient(): int
    {
        return 5;
    }

    public ?int $selectedDoctorId = null;

    public string $phone = '';

    public ?int $familyId = null;

    public ?int $selectedPatientId = null;

    public bool $showReserveModal = false;

    public bool $showReservedTokenModal = false;

    public bool $showNewPatientModal = false;

    public ?int $selectedTokenNumber = null;

    public ?int $selectedQueueTokenId = null;

    #[Rule('required|string|max:255')]
    public string $newPatientName = '';

    #[Rule('required|in:male,female')]
    public string $newPatientGender = 'male';

    #[Rule('required|date')]
    public string $newPatientDob = '';

    #[Rule('required|string|max:255')]
    public string $newPatientRelationToHead = 'self';

    public ?int $lastInvoiceId = null;

    public ?int $lastVisitId = null;

    public function updatedPhone(): void
    {
        $this->lookupFamily();
    }

    public function lookupFamily(): void
    {
        $normalized = preg_replace('/\D/', '', $this->phone);
        if ($normalized === '') {
            $this->familyId = null;
            $this->selectedPatientId = null;

            return;
        }
        $family = Family::where('phone', $this->phone)->with('patients')->first()
            ?? Family::where('phone', $normalized)->with('patients')->first();
        if ($family) {
            $this->familyId = $family->id;
            if ($this->selectedPatientId !== null) {
                $stillExists = $family->patients->contains('id', $this->selectedPatientId);
                if (! $stillExists) {
                    $this->selectedPatientId = null;
                }
            }
        } else {
            $this->familyId = null;
            $this->selectedPatientId = null;
        }
    }

    #[Computed]
    public function doctors()
    {
        return Doctor::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function queue(): ?Queue
    {
        if ($this->selectedDoctorId === null) {
            return null;
        }
        $shift = Shift::current();
        $q = Queue::where('service_id', self::appointmentServiceId())
            ->where('doctor_id', $this->selectedDoctorId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->first();
        if ($q !== null) {
            return $q;
        }

        $queueType = $this->selectedDoctorId === 1 ? 'shift' : 'daily';

        return Queue::create([
            'service_id' => self::appointmentServiceId(),
            'doctor_id' => $this->selectedDoctorId,
            'queue_type' => $queueType,
            'current_token' => 0,
            'status' => 'active',
            'started_at' => now(),
            'ended_at' => null,
            'shift_id' => $shift?->id,
            'created_by' => auth()->id(),
        ]);
    }

    #[Computed]
    public function schedule(): ?DoctorSchedule
    {
        if ($this->selectedDoctorId === null) {
            return null;
        }
        $day = strtolower(now()->format('l'));

        return DoctorSchedule::where('doctor_id', $this->selectedDoctorId)
            ->where('day', $day)
            ->first();
    }

    /**
     * @return array<int, array{token_number: int, expected_time: string, state: string, token: \App\Models\QueueToken|null}>
     */
    #[Computed]
    public function appointmentSlots(): array
    {
        $queue = $this->queue;
        if ($queue === null) {
            return [];
        }
        $tokensByNumber = QueueToken::where('queue_id', $queue->id)
            ->whereIn('token_number', range(1, self::slotCount()))
            ->with(['visit', 'patient'])
            ->get()
            ->keyBy('token_number');
        $schedule = $this->schedule;
        $result = [];
        for ($n = 1; $n <= self::slotCount(); $n++) {
            $token = $tokensByNumber->get($n);
            $expectedTime = $this->getExpectedTimeForSlot($n);
            if ($token !== null && $token->status === 'reserved') {
                $state = 'reserved';
            } elseif ($token !== null && $token->visit && $token->visit->status === 'confirmed') {
                $state = 'arrived';
            } elseif ($token !== null) {
                $state = 'unavailable';
            } else {
                $state = 'available';
            }
            $result[$n] = [
                'token_number' => $n,
                'expected_time' => $expectedTime,
                'state' => $state,
                'token' => $token,
            ];
        }

        return $result;
    }

    public function getExpectedTimeForSlot(int $tokenNumber): string
    {
        $schedule = $this->schedule;
        if ($schedule === null) {
            return 'N/A';
        }
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        $slotTime = $start->copy()->addMinutes(self::minutesPerPatient() * ($tokenNumber - 1));
        if ($slotTime->gt($end)) {
            return 'N/A';
        }

        return $slotTime->format('g:i A');
    }

    #[Computed]
    public function family(): ?Family
    {
        if ($this->familyId === null) {
            return null;
        }

        return Family::with('patients')->find($this->familyId);
    }

    #[Computed]
    public function patients()
    {
        $f = $this->family;
        if ($f === null) {
            return collect();
        }

        return $f->patients;
    }

    #[Computed]
    public function selectedReservedToken(): ?QueueToken
    {
        if ($this->selectedQueueTokenId === null) {
            return null;
        }

        return QueueToken::with(['visit.patient', 'visit.visitServices'])->find($this->selectedQueueTokenId);
    }

    #[Computed]
    public function appointmentServicePrice(): ?ServicePrice
    {
        if ($this->selectedDoctorId === null) {
            return null;
        }

        return ServicePrice::where('service_id', self::appointmentServiceId())
            ->where('doctor_id', $this->selectedDoctorId)
            ->first();
    }

    public function openReserveModal(int $tokenNumber): void
    {
        $this->selectedTokenNumber = $tokenNumber;
        $this->phone = '';
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->showReserveModal = true;
    }

    public function closeReserveModal(): void
    {
        $this->showReserveModal = false;
        $this->selectedTokenNumber = null;
    }

    public function openReservedTokenModal(int $queueTokenId): void
    {
        $this->selectedQueueTokenId = $queueTokenId;
        $this->showReservedTokenModal = true;
    }

    public function closeReservedTokenModal(): void
    {
        $this->showReservedTokenModal = false;
        $this->selectedQueueTokenId = null;
    }

    public function openNewPatientModal(): void
    {
        $this->resetValidation();
        $this->newPatientName = '';
        $this->newPatientGender = 'male';
        $this->newPatientDob = '';
        $this->newPatientRelationToHead = 'self';
        $this->showNewPatientModal = true;
    }

    public function saveNewPatient(): void
    {
        $this->validate();
        $normalizedPhone = preg_replace('/\D/', '', $this->phone);
        if ($normalizedPhone === '') {
            $this->addError('phone', __('Phone number is required to add a member.'));

            return;
        }
        $family = $this->familyId !== null ? Family::find($this->familyId) : null;
        if ($family === null) {
            $family = Family::create([
                'phone' => $normalizedPhone !== '' ? $normalizedPhone : $this->phone,
                'head_id' => null,
            ]);
            $this->familyId = $family->id;
        }
        $patient = Patient::create([
            'name' => $this->newPatientName,
            'gender' => $this->newPatientGender,
            'dob' => $this->newPatientDob,
            'relation_to_head' => $this->newPatientRelationToHead,
            'family_id' => $family->id,
        ]);
        if (strtolower($this->newPatientRelationToHead) === 'self') {
            $family->update(['head_id' => $patient->id]);
        }
        $this->selectedPatientId = $patient->id;
        $this->showNewPatientModal = false;
        $this->reset('newPatientName', 'newPatientGender', 'newPatientDob', 'newPatientRelationToHead');
    }

    public function reserveSlot(): void
    {
        $this->validate([
            'selectedPatientId' => 'required|exists:patients,id',
        ]);
        if ($this->selectedTokenNumber === null || $this->selectedDoctorId === null) {
            return;
        }
        $queue = $this->queue;
        if ($queue === null) {
            return;
        }
        $existing = QueueToken::where('queue_id', $queue->id)
            ->where('token_number', $this->selectedTokenNumber)
            ->exists();
        if ($existing) {
            $this->addError('selectedTokenNumber', __('This slot is already reserved.'));

            return;
        }

        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('selectedTokenNumber', __('Open a shift first.'));

            return;
        }

        $userId = auth()->id();

        DB::transaction(function () use ($shift, $userId): void {
            $visit = Visit::create([
                'patient_id' => $this->selectedPatientId,
                'status' => 'reserved',
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            VisitService::create([
                'visit_id' => $visit->id,
                'service_id' => self::appointmentServiceId(),
                'doctor_id' => $this->selectedDoctorId,
                'status' => 'assigned',
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            QueueToken::create([
                'queue_id' => $this->queue->id,
                'visit_id' => $visit->id,
                'patient_id' => $this->selectedPatientId,
                'token_number' => $this->selectedTokenNumber,
                'status' => 'reserved',
                'reserved_at' => now(),
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
        });
        $this->closeReserveModal();
    }

    public function markArrived(): void
    {
        $token = $this->selectedReservedToken;
        if ($token === null || $token->status !== 'reserved') {
            return;
        }
        $visit = $token->visit;
        if ($visit === null) {
            return;
        }
        $servicePrice = $this->appointmentServicePrice;
        if ($servicePrice === null) {
            $this->addError('appointmentServicePrice', __('No price defined for this doctor.'));

            return;
        }

        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('appointmentServicePrice', __('Open a shift first.'));

            return;
        }

        $userId = auth()->id();
        $price = $servicePrice->price;
        $invoiceId = null;
        $visitId = $visit->id;
        DB::transaction(function () use ($token, $visit, $servicePrice, $price, $shift, $userId, &$invoiceId): void {
            $invoice = Invoice::create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'total_amount' => $price,
                'status' => 'paid',
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            $invoiceId = $invoice->id;
            InvoiceService::create([
                'serviceprice_id' => $servicePrice->id,
                'service_price_id' => $servicePrice->id,
                'invoice_id' => $invoice->id,
                'price' => $price,
                'discount' => 0,
                'final_amount' => $price,
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            $token->update([
                'status' => 'waiting',
                'paid_at' => now(),
            ]);
            $visit->update(['status' => 'confirmed']);
            $visit->visitServices()->where('service_id', self::appointmentServiceId())->update(['status' => 'waiting']);
        });
        $this->lastInvoiceId = $invoiceId;
        $this->lastVisitId = $visitId;
        $invoice = Invoice::find($invoiceId);
        if ($invoice !== null) {
            app(PrintReceipt::class)->forInvoice($invoice);
        }
        $this->closeReservedTokenModal();
    }

    public function printReceipt(): void
    {
        if ($this->lastInvoiceId === null) {
            return;
        }
        $invoice = Invoice::find($this->lastInvoiceId);
        if ($invoice !== null) {
            app(PrintReceipt::class)->forInvoice($invoice);
        }
    }

    public function printTokenReceipt(): void
    {
        $token = $this->selectedReservedToken;
        if ($token === null) {
            return;
        }
        $invoice = $token->visit?->invoice;
        if ($invoice !== null) {
            app(PrintReceipt::class)->forInvoice($invoice);
        }
    }

    public function dismissReceipt(): void
    {
        $this->lastInvoiceId = null;
        $this->lastVisitId = null;
    }
};
?>

<div class="p-6 space-y-6" wire:poll.5s>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl">{{ __('Appointments') }}</flux:heading>
        <div class="w-full sm:w-64">
            <flux:select wire:model.live="selectedDoctorId" label="{{ __('Doctor') }}">
                <flux:select.option value="">{{ __('Choose doctor…') }}</flux:select.option>
                @foreach ($this->doctors as $doctor)
                    <flux:select.option value="{{ $doctor->id }}">{{ $doctor->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($lastInvoiceId !== null && $lastVisitId !== null)
        <flux:card class="p-4 border border-green-200 dark:border-green-800 bg-green-50/50 dark:bg-green-900/20">
            <flux:heading size="lg" class="mb-2">{{ __('Receipt confirmed') }}</flux:heading>
            <flux:subheading class="mb-3">{{ __('Invoice #:id · Visit #:visit', ['id' => $lastInvoiceId, 'visit' => $lastVisitId]) }}</flux:subheading>
            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="printReceipt">{{ __('Print receipt') }}</flux:button>
                <flux:button variant="ghost" wire:click="dismissReceipt">{{ __('Done') }}</flux:button>
            </div>
        </flux:card>
    @endif

    @if ($selectedDoctorId !== null)
        <div class="grid grid-cols-5 gap-3">
            @foreach ($this->appointmentSlots as $slot)
                @php
                    $state = $slot['state'];
                    $token = $slot['token'];
                @endphp
                <div
                    wire:key="slot-{{ $slot['token_number'] }}"
                    role="button"
                    tabindex="0"
                    wire:click="@if($state === 'available') openReserveModal({{ $slot['token_number'] }}) @elseif($state === 'reserved' || $state === 'arrived') openReservedTokenModal({{ $token->id }}) @endif"
                    class="relative flex flex-col rounded-lg border p-3 min-h-[100px] transition
                        {{ $state === 'available' ? 'cursor-pointer border-green-500/50 bg-green-500/20 hover:bg-green-500/30' : '' }}
                        {{ $state === 'reserved' ? 'cursor-pointer border-green-500/50 bg-green-500/20 hover:bg-green-500/30' : '' }}
                        {{ $state === 'arrived' ? 'cursor-pointer border-zinc-400/50 bg-zinc-600/40 dark:bg-zinc-500/30' : '' }}
                        {{ $state === 'unavailable' ? 'border-red-500/50 bg-red-500/20 cursor-not-allowed opacity-75' : '' }}"
                >
                    <div class="absolute top-2 right-2">
                        <flux:icon.user class="size-4 text-white/80" />
                    </div>
                    <span class="text-2xl font-bold text-white">{{ $slot['token_number'] }}</span>
                    <span class="text-sm text-white/90 mt-1">{{ $slot['expected_time'] }}</span>
                    @if ($state === 'reserved' && $token?->patient)
                        <span class="text-sm font-medium text-white mt-1 truncate">{{ $token->patient->name }}</span>
                        @if ($this->appointmentServicePrice)
                            <span class="text-xs text-white/80">Rs {{ number_format($this->appointmentServicePrice->price) }}</span>
                        @endif
                        <flux:button variant="primary" size="sm" class="mt-2 w-full justify-center" wire:click.stop="openReservedTokenModal({{ $token->id }})">
                            {{ __('Details') }}
                        </flux:button>
                    @endif
                    @if ($state === 'arrived' && $token?->patient)
                        <span class="text-sm font-medium text-white mt-1 truncate">{{ $token->patient->name }}</span>
                        @if ($this->appointmentServicePrice)
                            <span class="text-xs text-white/80">Rs {{ number_format($this->appointmentServicePrice->price) }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-green-400">
                            <flux:icon.check class="size-3.5" /> {{ __('Arrived') }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <flux:card class="p-8 text-center">
            <flux:subheading>{{ __('Select a doctor to view appointment slots.') }}</flux:subheading>
        </flux:card>
    @endif

    {{-- Reserve slot modal --}}
    <flux:modal wire:model.self="showReserveModal" name="reserve-slot-modal" focusable class="max-w-xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Reserve slot') }} #{{ $selectedTokenNumber }}</flux:heading>
            <flux:input
                icon="phone"
                wire:model.live.debounce.400ms="phone"
                label="{{ __('Patient Phone Number') }}"
                type="tel"
                placeholder="0308-4447764"
            />
            @if ($phone !== '')
                <div class="flex flex-wrap gap-2 items-center">
                    @foreach ($this->patients as $patient)
                        @php
                            $isHead = $this->family && $this->family->head_id === $patient->id;
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('selectedPatientId', {{ $patient->id }})"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition {{ $selectedPatientId === $patient->id ? 'bg-blue-600 text-white' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100' }}"
                        >
                            {{ $patient->name }}
                            @if ($isHead)
                                <span class="text-xs opacity-90">({{ __('Primary') }})</span>
                            @endif
                        </button>
                    @endforeach
                    <flux:button wire:click="openNewPatientModal" class="text-sm cursor-pointer">
                        + {{ __('Add New Member') }}
                    </flux:button>
                </div>
            @endif
            @if ($errors->isNotEmpty())
                <flux:callout variant="danger" icon="x-circle">
                    {{ $errors->first() }}
                </flux:callout>
            @endif
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="filled" wire:click="closeReserveModal">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="reserveSlot" :disabled="$selectedPatientId === null">
                    {{ __('Reserve') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reserved token detail modal (Arrived + Print) --}}
    <flux:modal wire:model.self="showReservedTokenModal" name="reserved-token-modal" focusable class="max-w-md">
        @if ($this->selectedReservedToken)
            @php
                $qt = $this->selectedReservedToken;
                $patient = $qt->patient;
                $price = $this->appointmentServicePrice?->price ?? 0;
                $isReserved = $qt->status === 'reserved';
            @endphp
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Token') }} #{{ $qt->token_number }}</flux:heading>
                <div class="space-y-2">
                    <p class="text-sm"><span class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Patient') }}:</span> {{ $patient?->name }}</p>
                    <p class="text-sm"><span class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Price') }}:</span> Rs {{ number_format($price) }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    @if ($isReserved)
                        <flux:button variant="primary" wire:click="markArrived">
                            {{ __('Arrived') }} &amp; {{ __('Print') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" wire:click="printTokenReceipt">{{ __('Print') }}</flux:button>
                    @endif
                    <flux:button variant="filled" wire:click="closeReservedTokenModal">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Add new member modal --}}
    <flux:modal wire:model.self="showNewPatientModal" name="appointment-new-patient-modal" focusable class="max-w-xl">
        <form wire:submit="saveNewPatient" class="space-y-4">
            <flux:heading size="lg">{{ __('Add New Member') }}</flux:heading>
            <flux:input wire:model="newPatientName" label="{{ __('Name') }}" required />
            <div>
                <label class="flux-label block text-sm font-medium mb-1">{{ __('Gender') }}</label>
                <select wire:model="newPatientGender" class="flux-input w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800" required>
                    <option value="male">{{ __('Male') }}</option>
                    <option value="female">{{ __('Female') }}</option>
                </select>
            </div>
            <flux:input wire:model="newPatientDob" label="{{ __('Date of birth') }}" type="date" required />
            <flux:input wire:model="newPatientRelationToHead" label="{{ __('Relation to head') }}" placeholder="self, spouse, child..." />
            @if ($errors->isNotEmpty())
                <flux:callout variant="danger" icon="x-circle">
                    {{ $errors->first() }}
                </flux:callout>
            @endif
            <div class="flex justify-end gap-2">
                <flux:button variant="filled" type="button" wire:click="$set('showNewPatientModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
