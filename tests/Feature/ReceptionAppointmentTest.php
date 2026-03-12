<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitService;
use App\Printing\ReceiptPrinterConnectorFactory;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $printerMock = \Mockery::mock();
    $printerMock->shouldReceive('text')->with(\Mockery::type('string'))->andReturnSelf();
    $printerMock->shouldReceive('cut')->andReturnSelf();
    $printerMock->shouldReceive('close')->andReturnNull();

    $factoryMock = \Mockery::mock(ReceiptPrinterConnectorFactory::class);
    $factoryMock->shouldReceive('printer')->with(\Mockery::type('string'))->andReturn($printerMock);

    $this->app->instance(ReceiptPrinterConnectorFactory::class, $factoryMock);
});

beforeEach(function () {
    if (Service::find(1) === null) {
        DB::table('services')->insert([
            'id' => 1,
            'name' => 'Consultation',
            'is_standalone' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
});

test('reception appointment page requires authentication', function () {
    $response = $this->get(route('reception.appointment'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception appointment page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.appointment'));
    $response->assertOk();
});

test('selecting doctor shows token grid and reserve flow creates visit and token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $doctor = Doctor::factory()->create(['status' => 'active']);
    $day = strtolower(now()->format('l'));
    DoctorSchedule::create([
        'doctor_id' => $doctor->id,
        'day' => $day,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    ServicePrice::factory()->create([
        'service_id' => 1,
        'doctor_id' => $doctor->id,
        'price' => 500,
    ]);

    $family = Family::create(['phone' => '03084447764', 'head_id' => null]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'relation_to_head' => 'self',
    ]);
    $family->update(['head_id' => $patient->id]);

    $component = Livewire::test('pages::reception.appointment')
        ->set('selectedDoctorId', $doctor->id);

    expect($component->get('queue'))->not->toBeNull();
    $slots = $component->get('appointmentSlots');
    expect($slots)->toHaveCount(50);

    $component
        ->call('openReserveModal', 5)
        ->set('phone', '03084447764')
        ->set('selectedPatientId', $patient->id)
        ->call('reserveSlot')
        ->assertHasNoErrors();

    expect(Visit::where('patient_id', $patient->id)->count())->toBe(1);
    $visit = Visit::where('patient_id', $patient->id)->first();
    expect($visit->status)->toEqual('reserved');

    expect(VisitService::where('visit_id', $visit->id)->count())->toBe(1);
    $visitSvc = VisitService::where('visit_id', $visit->id)->first();
    expect($visitSvc->service_id)->toEqual(1);
    expect($visitSvc->doctor_id)->toEqual($doctor->id);
    expect($visitSvc->status)->toEqual('assigned');

    expect(QueueToken::where('visit_id', $visit->id)->count())->toBe(1);
    $token = QueueToken::where('visit_id', $visit->id)->first();
    expect($token->token_number)->toEqual(5);
    expect($token->status)->toEqual('reserved');
    expect($token->reserved_at)->not->toBeNull();
    expect($visit->shift_id)->not->toBeNull();
    expect($visit->created_by)->toEqual($user->id);
    expect($token->shift_id)->toEqual($visit->shift_id);
    expect($token->created_by)->toEqual($user->id);
});

test('arrived flow creates invoice and updates token and visit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $doctor = Doctor::factory()->create(['status' => 'active']);
    $day = strtolower(now()->format('l'));
    DoctorSchedule::create([
        'doctor_id' => $doctor->id,
        'day' => $day,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    ServicePrice::factory()->create([
        'service_id' => 1,
        'doctor_id' => $doctor->id,
        'price' => 500,
    ]);

    $queue = Queue::create([
        'service_id' => 1,
        'doctor_id' => $doctor->id,
        'queue_type' => 'daily',
        'current_token' => 0,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $family = Family::create(['phone' => '03084447764', 'head_id' => null]);
    $patient = Patient::factory()->create(['family_id' => $family->id]);

    $visit = Visit::create([
        'patient_id' => $patient->id,
        'status' => 'reserved',
    ]);
    VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => 1,
        'doctor_id' => $doctor->id,
        'status' => 'assigned',
    ]);
    $queueToken = QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'token_number' => 3,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);

    Livewire::test('pages::reception.appointment')
        ->set('selectedDoctorId', $doctor->id)
        ->call('openReservedTokenModal', $queueToken->id)
        ->call('markArrived')
        ->assertHasNoErrors();

    expect(Invoice::where('visit_id', $visit->id)->count())->toBe(1);
    $invoice = Invoice::where('visit_id', $visit->id)->first();
    expect($invoice->total_amount)->toEqual(500);
    expect($invoice->status)->toEqual('paid');
    expect($invoice->shift_id)->not->toBeNull();
    expect($invoice->created_by)->toEqual($user->id);

    expect(InvoiceService::where('invoice_id', $invoice->id)->count())->toBe(1);
    $invSvc = InvoiceService::where('invoice_id', $invoice->id)->first();
    expect($invSvc->price)->toEqual(500);

    $queueToken->refresh();
    expect($queueToken->status)->toEqual('waiting');
    expect($queueToken->paid_at)->not->toBeNull();

    $visit->refresh();
    expect($visit->status)->toEqual('confirmed');

    $visit->visitServices()->where('service_id', 1)->first();
    $vs = VisitService::where('visit_id', $visit->id)->where('service_id', 1)->first();
    expect($vs->status)->toEqual('waiting');
});
