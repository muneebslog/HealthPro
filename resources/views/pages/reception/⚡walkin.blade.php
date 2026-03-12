<?php

use App\Actions\PrintReceipt;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\Visit;
use App\Models\VisitService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Rule;

new class extends Component
{
    public string $phone = '';

    public ?int $selectedPatientId = null;

    public ?int $familyId = null;

    /** @var array<int, array{id: string, service_id: int, doctor_id: ?int, serviceprice_id: int, service_name: string, doctor_name: string, price: int, token_number: int, token_display: string}> */
    public array $activeRows = [];

    public ?int $selectedServiceId = null;

    public ?int $selectedDoctorId = null;

    public ?int $selectedServicePriceId = null;

    public string $selectedPrice = '';

    public bool $showNewPatientModal = false;

    public bool $showEditPriceModal = false;

    public ?int $editPriceRowIndex = null;

    public string $editPriceValue = '';

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
    public function services()
    {
        return Service::orderBy('name')->get(['id', 'name', 'is_standalone']);
    }

    #[Computed]
    public function servicePricesForSelectedService()
    {
        if ($this->selectedServiceId === null) {
            return collect();
        }

        return ServicePrice::where('service_id', $this->selectedServiceId)
            ->with('doctor')
            ->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        if ($this->selectedServiceId === null) {
            return null;
        }

        return Service::find($this->selectedServiceId);
    }

    /**
     * Get the next available token number for a queue (skips any already used).
     */
    public function getNextAvailableTokenForQueue(Queue $queue): int
    {
        $next = max($queue->current_token + 1, 1);

        while (QueueToken::where('queue_id', $queue->id)->where('token_number', $next)->exists()) {
            $next++;
        }

        return $next;
    }

    public function getNextTokenFor(int $serviceId, ?int $doctorId): int
    {
        $query = Queue::where('service_id', $serviceId)
            ->where('status', 'active')
            ->whereNull('ended_at');

        if ($doctorId === null) {
            $query->whereNull('doctor_id');
        } else {
            $query->where('doctor_id', $doctorId);
        }

        $queue = $query->first();

        if ($queue === null) {
            return 1;
        }

        return $this->getNextAvailableTokenForQueue($queue);
    }

    public function addService(): void
    {
        if ($this->selectedPatientId === null || $this->selectedServicePriceId === null || $this->selectedPrice === '') {
            return;
        }
        $price = (int) $this->selectedPrice;
        if ($price < 0) {
            return;
        }
        $sp = ServicePrice::with(['service', 'doctor'])->find($this->selectedServicePriceId);
        if ($sp === null) {
            return;
        }
        $serviceId = $sp->service_id;
        $doctorId = $sp->doctor_id;
        $tokenNum = $this->getNextTokenFor($serviceId, $doctorId);
        $serviceName = $sp->service->name ?? 'Service';
        $doctorName = $sp->doctor?->name ?? '—';
        $prefix = strtoupper(substr($serviceName, 0, 2));
        if ($prefix === '') {
            $prefix = 'SV';
        }
        $tokenDisplay = $prefix.'-'.$tokenNum;
        $this->activeRows[] = [
            'id' => uniqid('row', true),
            'service_id' => $serviceId,
            'doctor_id' => $doctorId,
            'serviceprice_id' => $sp->id,
            'service_name' => $serviceName,
            'doctor_name' => $doctorName,
            'price' => $price,
            'token_number' => $tokenNum,
            'token_display' => $tokenDisplay,
        ];
        $this->selectedServiceId = null;
        $this->selectedDoctorId = null;
        $this->selectedServicePriceId = null;
        $this->selectedPrice = '';
    }

    public function removeRow(int $index): void
    {
        if (isset($this->activeRows[$index])) {
            array_splice($this->activeRows, $index, 1);
        }
    }

    public function openEditPriceModal(int $index): void
    {
        if (isset($this->activeRows[$index])) {
            $this->editPriceRowIndex = $index;
            $this->editPriceValue = (string) $this->activeRows[$index]['price'];
            $this->showEditPriceModal = true;
        }
    }

    public function openEditSelectedPriceModal(): void
    {
        $this->editPriceRowIndex = null;
        $this->editPriceValue = $this->selectedPrice;
        $this->showEditPriceModal = true;
    }

    public function saveEditPrice(): void
    {
        $v = (int) $this->editPriceValue;
        if ($v < 0) {
            $this->showEditPriceModal = false;
            $this->editPriceRowIndex = null;
            $this->editPriceValue = '';

            return;
        }
        if ($this->editPriceRowIndex !== null && isset($this->activeRows[$this->editPriceRowIndex])) {
            $this->activeRows[$this->editPriceRowIndex]['price'] = $v;
        } elseif ($this->editPriceRowIndex === null) {
            $this->selectedPrice = (string) $v;
        }
        $this->showEditPriceModal = false;
        $this->editPriceRowIndex = null;
        $this->editPriceValue = '';
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
            $normalizedPhone = preg_replace('/\D/', '', $this->phone);
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

    public function updatedSelectedServiceId(): void
    {
        $this->selectedDoctorId = null;
        $this->selectedServicePriceId = null;
        $this->selectedPrice = '';
        $prices = $this->servicePricesForSelectedService;
        $svc = $this->selectedService;
        if ($svc && $svc->is_standalone && $prices->isNotEmpty()) {
            $first = $prices->first();
            $this->selectedServicePriceId = $first->id;
            $this->selectedPrice = (string) $first->price;
        }
    }

    public function updatedSelectedServicePriceId(): void
    {
        if ($this->selectedServicePriceId !== null) {
            $sp = ServicePrice::find($this->selectedServicePriceId);
            if ($sp) {
                $this->selectedDoctorId = $sp->doctor_id;
                $this->selectedPrice = (string) $sp->price;
            }
        }
    }

    /**
     * Resolve queue for service+doctor without creating. Returns null if no active queue.
     */
    private function resolveQueueFor(int $serviceId, ?int $doctorId): ?Queue
    {
        $query = Queue::where('service_id', $serviceId)
            ->where('status', 'active')
            ->whereNull('ended_at');

        if ($doctorId === null) {
            $query->whereNull('doctor_id');
        } else {
            $query->where('doctor_id', $doctorId);
        }

        return $query->first();
    }

    /**
     * Re-check token numbers at confirm time. If any were taken by another user, refresh UI and abort.
     */
    private function refreshActiveRowsTokens(): void
    {
        $allocated = [];
        foreach ($this->activeRows as $index => $row) {
            $serviceId = $row['service_id'];
            $doctorId = $row['doctor_id'] ?? null;
            $queue = $this->resolveQueueFor($serviceId, $doctorId);
            if ($queue === null) {
                $tokenNum = 1;
            } else {
                $tokenNum = $allocated[$queue->id] ?? $this->getNextAvailableTokenForQueue($queue);
                $allocated[$queue->id] = $tokenNum + 1;
            }
            $prefix = strtoupper(substr($row['service_name'], 0, 2)) ?: 'SV';
            $this->activeRows[$index]['token_number'] = $tokenNum;
            $this->activeRows[$index]['token_display'] = $prefix.'-'.$tokenNum;
        }
    }

    public function confirmAndPrintReceipt(): void
    {
        $this->validate([
            'selectedPatientId' => 'required|exists:patients,id',
        ]);
        if (empty($this->activeRows)) {
            $this->addError('activeRows', __('Add at least one service.'));

            return;
        }

        $shift = Shift::current();
        if ($shift === null) {
            $this->addError('activeRows', __('Open a shift first.'));

            return;
        }

        $userId = auth()->id();

        $allocated = [];
        $tokenTakenByOther = false;
        foreach ($this->activeRows as $row) {
            $serviceId = $row['service_id'];
            $doctorId = $row['doctor_id'] ?? null;
            $queue = $this->resolveQueueFor($serviceId, $doctorId);
            if ($queue === null) {
                $nextNow = 1;
            } else {
                $nextNow = $allocated[$queue->id] ?? $this->getNextAvailableTokenForQueue($queue);
                $allocated[$queue->id] = $nextNow + 1;
            }
            if ($nextNow !== ($row['token_number'] ?? null)) {
                $tokenTakenByOther = true;
                break;
            }
        }

        if ($tokenTakenByOther) {
            $this->refreshActiveRowsTokens();
            $this->addError('activeRows', __('One or more token numbers were taken by another user. Token numbers have been updated. Please review and confirm again.'));

            return;
        }

        $subtotal = array_sum(array_column($this->activeRows, 'price'));
        DB::transaction(function () use ($subtotal, $shift, $userId): void {
            $visit = Visit::create([
                'patient_id' => $this->selectedPatientId,
                'status' => 'confirmed',
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            $invoice = Invoice::create([
                'patient_id' => $this->selectedPatientId,
                'visit_id' => $visit->id,
                'total_amount' => $subtotal,
                'status' => 'paid',
                'shift_id' => $shift->id,
                'created_by' => $userId,
            ]);
            foreach ($this->activeRows as $row) {
                $serviceId = $row['service_id'];
                $doctorId = $row['doctor_id'] ?? null;
                $queue = Queue::where('service_id', $serviceId)
                    ->where('status', 'active')
                    ->whereNull('ended_at');
                if ($doctorId === null) {
                    $queue->whereNull('doctor_id');
                } else {
                    $queue->where('doctor_id', $doctorId);
                }
                $queue = $queue->first();
                if ($queue === null) {
                    $queueType = ($serviceId === Queue::APPOINTMENT_SERVICE_ID && $doctorId === 1) ? 'shift' : 'daily';
                    $queue = Queue::create([
                        'service_id' => $serviceId,
                        'doctor_id' => $doctorId,
                        'queue_type' => $queueType,
                        'current_token' => 0,
                        'status' => 'active',
                        'started_at' => now(),
                        'ended_at' => null,
                        'shift_id' => $shift->id,
                        'created_by' => $userId,
                    ]);
                }
                $nextToken = $this->getNextAvailableTokenForQueue($queue);
                QueueToken::create([
                    'queue_id' => $queue->id,
                    'visit_id' => $visit->id,
                    'patient_id' => $this->selectedPatientId,
                    'token_number' => $nextToken,
                    'status' => 'waiting',
                    'reserved_at' => now(),
                    'shift_id' => $shift->id,
                    'created_by' => $userId,
                ]);
                $queue->update(['current_token' => $nextToken]);
                VisitService::create([
                    'visit_id' => $visit->id,
                    'service_id' => $serviceId,
                    'doctor_id' => $doctorId,
                    'status' => 'assigned',
                    'shift_id' => $shift->id,
                    'created_by' => $userId,
                ]);
                InvoiceService::create([
                    'serviceprice_id' => $row['serviceprice_id'],
                    'service_price_id' => $row['serviceprice_id'],
                    'invoice_id' => $invoice->id,
                    'price' => $row['price'],
                    'discount' => 0,
                    'final_amount' => $row['price'],
                    'shift_id' => $shift->id,
                    'created_by' => $userId,
                ]);
            }
            $this->lastVisitId = $visit->id;
            $this->lastInvoiceId = $invoice->id;

            app(PrintReceipt::class)->forInvoice($invoice);
        });
        $this->activeRows = [];
        $this->selectedPatientId = null;
        $this->selectedServiceId = null;
        $this->selectedDoctorId = null;
        $this->selectedServicePriceId = null;
        $this->selectedPrice = '';
    }

    public function clearSession(): void
    {
        $this->phone = '';
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->activeRows = [];
        $this->selectedServiceId = null;
        $this->selectedDoctorId = null;
        $this->selectedServicePriceId = null;
        $this->selectedPrice = '';
        $this->lastInvoiceId = null;
        $this->lastVisitId = null;
    }

    public function dismissReceipt(): void
    {
        $this->lastInvoiceId = null;
        $this->lastVisitId = null;
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
    public function subtotal(): int
    {
        return (int) array_sum(array_column($this->activeRows, 'price'));
    }

    #[Computed]
    public function currentShift(): ?Shift
    {
        return Shift::current();
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center gap-2">
        <flux:heading size="xl">Front Desk Reception</flux:heading>
    </div>

    @if ($this->currentShift === null)
        <flux:callout variant="danger" icon="exclamation-triangle" class="border-amber-500/50 bg-amber-50 dark:bg-amber-950/30">
            <flux:heading size="md">{{ __('No shift is open') }}</flux:heading>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('You must open a shift before registering walk-in patients and printing receipts.') }}
            </p>
            <flux:link :href="route('reception.shift')" wire:navigate class="mt-3 inline-flex font-medium text-amber-700 dark:text-amber-300 hover:underline">
                {{ __('Open a shift') }}
            </flux:link>
        </flux:callout>
    @endif

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

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Patient card --}}
            <flux:card class="p-5">
                <flux:heading size="lg" class="mb-4">{{ __('Patient Information') }}</flux:heading>
                <div class="space-y-4">
                    <div class="relative">
                        <flux:input
                        icon="phone"
                            wire:model.live.debounce.400ms="phone"
                            label="{{ __('Patient Phone Number') }}"
                            type="tel"
                            placeholder="0308-4447764"
                            class="pe-10"
                        />
                    </div>
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
                </div>
            </flux:card>

            {{-- Service and doctor selection --}}
            <flux:card class="p-5">
                <flux:heading size="lg" class="mb-4">{{ __('Select Service & Doctor') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <flux:select wire:model.live="selectedServiceId" label="{{ __('Choose a service...') }}">
                            <flux:select.option value="">{{ __('Choose a service...') }}</flux:select.option>
                            @foreach ($this->services as $svc)
                                <flux:select.option value="{{ $svc->id }}">{{ $svc->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if ($this->selectedService && ! $this->selectedService->is_standalone)
                        <div>
                            <flux:select wire:model.live="selectedServicePriceId" label="{{ __('Choose Doctor...') }}">
                                <flux:select.option value="">{{ __('Choose Doctor...') }}</flux:select.option>
                                @foreach ($this->servicePricesForSelectedService as $sp)
                                    <flux:select.option value="{{ $sp->id }}">{{ $sp->doctor?->name ?? '—' }} — {{ number_format($sp->price) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                    @if ($this->selectedService && $this->selectedService->is_standalone && $this->servicePricesForSelectedService->isNotEmpty())
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:subheading class="mb-1">{{ __('Price') }}</flux:subheading>
                                <span class="text-lg font-semibold">${{ number_format((int) $selectedPrice, 2) }}</span>
                            </div>
                            <flux:button variant="ghost" size="sm" wire:click="openEditSelectedPriceModal">
                                {{ __('Edit') }}
                            </flux:button>
                        </div>
                    @endif
                    @if ($selectedServicePriceId !== null && $this->selectedService && ! $this->selectedService->is_standalone)
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:subheading class="mb-1">{{ __('Price') }}</flux:subheading>
                                <span class="text-lg font-semibold">${{ number_format((int) $selectedPrice, 2) }}</span>
                            </div>
                            <flux:button variant="ghost" size="sm" wire:click="openEditSelectedPriceModal">
                                {{ __('Edit') }}
                            </flux:button>
                        </div>
                    @endif
                    <div class="sm:col-span-2 lg:col-span-1 flex items-end">
                        <flux:button
                            variant="primary"
                            wire:click="addService"
                            {{-- disabled="{{ $selectedPatientId === null || $selectedServicePriceId === null || $selectedPrice === '' ? 'true' : 'false' }}" --}}
                        >
                            {{ __('Add Service') }}
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            {{-- Active services table --}}
            <flux:card class="p-5 overflow-hidden">
                <flux:heading size="lg" class="mb-4">{{ __('Active Services') }}</flux:heading>
                @if (empty($activeRows))
                    <flux:subheading>{{ __('No services added yet.') }}</flux:subheading>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="pb-2 text-xs font-semibold uppercase text-zinc-500">{{ __('SERVICE NAME') }}</th>
                                    <th class="pb-2 text-xs font-semibold uppercase text-zinc-500">{{ __('DOCTOR') }}</th>
                                    <th class="pb-2 text-xs font-semibold uppercase text-zinc-500">{{ __('TOKEN') }}</th>
                                    <th class="pb-2 text-xs font-semibold uppercase text-zinc-500">{{ __('PRICE') }}</th>
                                    <th class="pb-2 text-xs font-semibold uppercase text-zinc-500">{{ __('ACTION') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activeRows as $index => $row)
                                    <tr class="border-b border-zinc-100 dark:border-zinc-700/50" wire:key="row-{{ $row['id'] }}">
                                        <td class="py-3">{{ $row['service_name'] }}</td>
                                        <td class="py-3">{{ $row['doctor_name'] }}</td>
                                        <td class="py-3">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200">
                                                {{ $row['token_display'] }}
                                            </span>
                                        </td>
                                        <td class="py-3">${{ number_format($row['price'], 2) }}</td>
                                        <td class="py-3 flex items-center gap-1">
                                            <flux:button variant="ghost" size="sm" wire:click="openEditPriceModal({{ $index }})">
                                                <flux:icon.pencil-square class="size-4" />
                                            </flux:button>
                                            <flux:button variant="ghost" size="sm" wire:click="removeRow({{ $index }})">
                                                <flux:icon.trash class="size-4 text-red-500" />
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Billing summary --}}
        <div class="lg:col-span-1">
            <flux:card class="p-5 sticky top-4">
                <flux:heading size="lg" class="mb-4">{{ __('Billing Summary') }}</flux:heading>
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                        <span>${{ number_format($this->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold text-blue-600 dark:text-blue-400 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                        <span>{{ __('Total Bill') }}</span>
                        <span>${{ number_format($this->subtotal, 2) }}</span>
                    </div>
                </div>
                <flux:button
                    icon="printer"
                    variant="primary"
                    class="w-full justify-center"
                    wire:click="confirmAndPrintReceipt"
                    {{-- disabled="{{ $selectedPatientId === null || empty($activeRows) ? 'true' : 'false' }}" --}}
                >
                    {{ __('Confirm & Print Receipt') }}
                </flux:button>
                <flux:link wire:click="clearSession" class="mt-3 inline-block text-sm cursor-pointer">
                    {{ __('Clear Session') }}
                </flux:link>
            </flux:card>
        </div>
    </div>

    {{-- New patient modal --}}
    
        <flux:modal wire:model.self="showNewPatientModal" name="new-patient-modal"  focusable class="max-w-xl">
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


    {{-- Edit price modal (for selection or row) --}}
        <flux:modal wire:model.self="showEditPriceModal" name="edit-price-modal"  focusable class="max-w-sm">
            <form wire:submit="saveEditPrice" class="space-y-4">
                <flux:heading size="lg">{{ __('Edit Price') }}</flux:heading>
                <flux:input wire:model="editPriceValue" label="{{ __('Price') }}" type="number" min="0" step="1" />
                <div class="flex justify-end gap-2">
                    <flux:button variant="filled" type="button" wire:click="$set('showEditPriceModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
