<?php

use App\Actions\PrintReceipt;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\ProcedureAdmission;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Rule;

new class extends Component {
    public string $phone = '';

    public ?int $selectedPatientId = null;

    public ?int $familyId = null;

    #[Rule('required|string|max:255')]
    public string $packageName = '';

    #[Rule('required|integer|min:0')]
    public int $fullPrice = 0;

    public ?int $operationDoctorId = null;

    #[Rule('nullable|date')]
    public ?string $operationDate = null;

    public string $room = '';

    public string $bed = '';

    #[Rule('required|integer|min:0')]
    public int $advancePayment = 0;

    public bool $showNewPatientModal = false;

    public ?int $lastInvoiceId = null;

    public ?int $lastProcedureAdmissionId = null;

    #[Rule('required|string|max:255')]
    public string $newPatientName = '';

    #[Rule('required|in:male,female')]
    public string $newPatientGender = 'male';

    #[Rule('required|integer|min:0|max:120')]
    public int $newPatientAge = 0;

    #[Rule('required|in:self,son,daughter,brother,sister,wife,husband,others,random')]
    public string $newPatientRelationToHead = 'self';

    public function updatedPhone(): void
    {
        $normalized = preg_replace('/\D/', '', $this->phone);
        if ($normalized === '') {
            $this->familyId = null;
            $this->selectedPatientId = null;

            return;
        }

        if (strlen($normalized) === 11) {
            $this->lookupFamily();
        }
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
    public function doctors()
    {
        return Doctor::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function remainingBalance(): int
    {
        return max(0, $this->fullPrice - $this->advancePayment);
    }

    public function openNewPatientModal(): void
    {
        $this->resetValidation();
        $this->newPatientName = '';
        $this->newPatientGender = 'male';
        $this->newPatientAge = 0;
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
            $normalizedPhone = preg_replace('/\D/', '', $this->phone);
            $family = Family::create([
                'phone' => $normalizedPhone !== '' ? $normalizedPhone : $this->phone,
                'head_id' => null,
            ]);
            $this->familyId = $family->id;
        }
        $age = (int) $this->newPatientAge;
        $dob = now()->subYears($age)->startOfDay();

        $patient = Patient::create([
            'name' => $this->newPatientName,
            'gender' => $this->newPatientGender,
            'age' => $age,
            'dob' => $dob->toDateString(),
            'relation_to_head' => $this->newPatientRelationToHead,
            'family_id' => $family->id,
        ]);
        if (strtolower($this->newPatientRelationToHead) === 'self') {
            $family->update(['head_id' => $patient->id]);
        }
        $this->selectedPatientId = $patient->id;
        $this->showNewPatientModal = false;
        $this->reset('newPatientName', 'newPatientGender', 'newPatientAge', 'newPatientRelationToHead');
    }

    public function confirmAndPrintReceipt(): void
    {
        $this->validate([
            'selectedPatientId' => 'required|exists:patients,id',
            'packageName' => 'required|string|max:255',
            'fullPrice' => 'required|integer|min:0',
            'advancePayment' => 'required|integer|min:0',
        ]);

        if ($this->advancePayment > $this->fullPrice) {
            $this->addError('advancePayment', __('Advance payment cannot exceed full price.'));

            return;
        }

        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('activeRows', __('Open a shift first.'));

            return;
        }

        $userId = auth()->id();
        $paidAmount = (int) $this->advancePayment;
        $status = $paidAmount >= $this->fullPrice ? 'paid' : ($paidAmount > 0 ? 'partialpaid' : 'unpaid');

        DB::transaction(function () use ($shift, $userId, $paidAmount, $status): void {
            $admission = ProcedureAdmission::create([
                'patient_id' => $this->selectedPatientId,
                'package_name' => $this->packageName,
                'full_price' => $this->fullPrice,
                'operation_doctor_id' => $this->operationDoctorId ?: null,
                'operation_date' => $this->operationDate ?: null,
                'room' => $this->room !== '' ? $this->room : null,
                'bed' => $this->bed !== '' ? $this->bed : null,
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);

            $invoice = Invoice::create([
                'patient_id' => $this->selectedPatientId,
                'visit_id' => null,
                'procedure_admission_id' => $admission->id,
                'total_amount' => $this->fullPrice,
                'paid_amount' => $paidAmount,
                'status' => $status,
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);

            if ($paidAmount > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $paidAmount,
                    'paid_at' => now(),
                    'shift_id' => $shift->id,
                    'created_by' => $userId,
                ]);
            }

            $this->lastProcedureAdmissionId = $admission->id;
            $this->lastInvoiceId = $invoice->id;

            // app(PrintReceipt::class)->forInvoice($invoice);
        });

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->packageName = '';
        $this->fullPrice = 0;
        $this->operationDoctorId = null;
        $this->operationDate = null;
        $this->room = '';
        $this->bed = '';
        $this->advancePayment = 0;
        $this->selectedPatientId = null;
    }

    public function clearSession(): void
    {
        $this->phone = '';
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->resetForm();
        $this->lastInvoiceId = null;
        $this->lastProcedureAdmissionId = null;
    }

    public function dismissReceipt(): void
    {
        $this->lastInvoiceId = null;
        $this->lastProcedureAdmissionId = null;
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

    #[Computed]
    public function currentShift(): ?Shift
    {
        return Shift::current();
    }
};
?>

@placeholder
<div class="p-6 space-y-6">
    <flux:skeleton.group animate="shimmer" class="space-y-6">
        <flux:skeleton.line class="h-8 w-56" />
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <flux:card class="p-5">
                    <flux:skeleton.line class="mb-4 w-40" />
                    <flux:skeleton class="h-10 w-full rounded-lg mb-4" />
                    <div class="flex gap-2">
                        <flux:skeleton class="h-8 w-24 rounded-full" />
                        <flux:skeleton class="h-8 w-28 rounded-full" />
                    </div>
                </flux:card>
                <flux:card class="p-5">
                    <flux:skeleton.line class="mb-4 w-44" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:skeleton class="h-10 rounded-lg" />
                        <flux:skeleton class="h-10 rounded-lg" />
                    </div>
                </flux:card>
            </div>
            <div>
                <flux:card class="p-5">
                    <flux:skeleton.line class="mb-4 w-36" />
                    <flux:skeleton.line class="mb-2 w-full" />
                    <flux:skeleton class="h-10 w-full rounded-lg" />
                </flux:card>
            </div>
        </div>
    </flux:skeleton.group>
</div>
@endplaceholder

<div class="p-6 space-y-6">
    <div class="flex items-center gap-2">
        <flux:heading size="xl">{{ __('Procedure Patient Receiving') }}</flux:heading>
    </div>

    @if ($this->currentShift === null)
        <flux:callout variant="danger" icon="exclamation-triangle"
            class="border-amber-500/50 bg-amber-50 dark:bg-amber-950/30">
            <flux:heading size="md">{{ __('No shift is open') }}</flux:heading>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('You must open a shift before registering procedure patients.') }}
            </p>
            <flux:link :href="route('reception.shift')" wire:navigate
                class="mt-3 inline-flex font-medium text-amber-700 dark:text-amber-300 hover:underline">
                {{ __('Open a shift') }}
            </flux:link>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Patient card --}}
            <flux:card class="p-5">
                <flux:heading size="lg" class="mb-4">{{ __('Patient Information') }}</flux:heading>
                <div class="space-y-4">
                    <div class="relative">
                        <flux:input icon="phone" wire:model.live.debounce.400ms="phone"
                            label="{{ __('Patient Phone Number') }}" mask="9999-99999999" placeholder="0320-8489685"
                            class="pe-10" />
                    </div>
                    @if ($phone !== '')
                        <div class="flex flex-wrap gap-2 items-center">
                            @foreach ($this->patients as $patient)
                                @php
                                    $isHead = $this->family && $this->family->head_id === $patient->id;
                                @endphp
                                <button type="button" wire:click="$set('selectedPatientId', {{ $patient->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition {{ $selectedPatientId === $patient->id ? 'bg-blue-600 text-white' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100' }}">
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
                </div>
            </flux:card>

            {{-- Procedure details --}}
            <flux:card class="p-5">
                <flux:heading size="lg" class="mb-4">{{ __('Procedure Details') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:input wire:model="packageName" label="{{ __('Package Name') }}" />
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="fullPrice" label="{{ __('Full Price') }}" type="number" min="0" />
                    </div>
                    <div>
                        <flux:select wire:model="operationDoctorId">
                            <flux:select.option value="">{{ __('Operation Doctor') }}</flux:select.option>
                            @foreach ($this->doctors as $doctor)
                                <flux:select.option value="{{ $doctor->id }}">{{ $doctor->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:input wire:model="operationDate" label="{{ __('Operation Date') }}" type="date" />
                    </div>
                    <div>
                        <flux:input wire:model="room" label="{{ __('Room') }}" placeholder="e.g. Room1" />
                    </div>
                    <div>
                        <flux:input wire:model="bed" label="{{ __('Bed') }}" placeholder="e.g. Ward Bed 2" />
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Payment summary --}}
        <div class="lg:col-span-1">
            <flux:card class="p-5 sticky top-4">
                <flux:heading size="lg" class="mb-4">{{ __('Payment Summary') }}</flux:heading>
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Full Price') }}</span>
                        <span>Rs {{ number_format($this->fullPrice) }}</span>
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="advancePayment" label="{{ __('Advance Payment') }}" type="number" min="0" />
                    </div>
                    <div class="flex justify-between text-sm pt-2">
                        <span class="text-zinc-500">{{ __('Remaining Balance') }}</span>
                        <span class="font-semibold">Rs {{ number_format($this->remainingBalance) }}</span>
                    </div>
                    <div
                        class="flex justify-between text-lg font-bold text-blue-600 dark:text-blue-400 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                        <span>{{ __('Total Bill') }}</span>
                        <span>Rs {{ number_format($this->fullPrice) }}</span>
                    </div>
                </div>
                <flux:button icon="printer" variant="primary" class="w-full justify-center"
                    wire:click="confirmAndPrintReceipt">
                    {{ __('Confirm & Print Receipt') }}
                </flux:button>
                <flux:link wire:click="clearSession" class="mt-3 inline-block text-sm cursor-pointer">
                    {{ __('Clear Session') }}
                </flux:link>
            </flux:card>
        </div>
    </div>

    {{-- New patient modal --}}
    <flux:modal wire:model.self="showNewPatientModal" name="new-patient-modal" focusable class="max-w-xl">
        <form wire:submit="saveNewPatient" class="space-y-4">
            <flux:heading size="lg">{{ __('Add New Member') }}</flux:heading>
            <flux:input wire:model="newPatientName" label="{{ __('Name') }}" required />
            <div>
                <label class="flux-label block text-sm font-medium mb-1">{{ __('Gender') }}</label>
                <select wire:model="newPatientGender"
                    class="flux-input w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800" required>
                    <option value="male">{{ __('Male') }}</option>
                    <option value="female">{{ __('Female') }}</option>
                </select>
            </div>
            <flux:input wire:model="newPatientAge" label="{{ __('Age') }}" type="number" min="0" max="120" required />
            <div>
                <label class="flux-label block text-sm font-medium mb-1">{{ __('Relation to head') }}</label>
                <select wire:model="newPatientRelationToHead"
                    class="flux-input w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800" required>
                    <option value="self">{{ __('Self') }}</option>
                    <option value="son">{{ __('Son') }}</option>
                    <option value="daughter">{{ __('Daughter') }}</option>
                    <option value="brother">{{ __('Brother') }}</option>
                    <option value="sister">{{ __('Sister') }}</option>
                    <option value="wife">{{ __('Wife') }}</option>
                    <option value="husband">{{ __('Husband') }}</option>
                    <option value="others">{{ __('Others') }}</option>
                    <option value="random">{{ __('Random') }}</option>
                </select>
            </div>
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
