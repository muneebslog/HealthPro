<?php

use App\Models\Doctor;
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

test('reception walk-in page requires authentication', function () {
    $response = $this->get(route('reception.walkin'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception walk-in page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.walkin'));
    $response->assertOk();
});

test('walk-in flow creates visit invoice and tokens when confirming', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $family = Family::create([
        'phone' => '03084447764',
        'head_id' => null,
    ]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'relation_to_head' => 'self',
    ]);
    $family->update(['head_id' => $patient->id]);

    $service = Service::factory()->create(['is_standalone' => false]);
    $doctor = Doctor::factory()->create(['status' => 'active']);
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'price' => 200,
    ]);

    $component = Livewire::test('pages::reception.walkin')
        ->set('phone', '03084447764');

    $component->assertSet('familyId', $family->id);
    $component->assertSet('selectedPatientId', null);

    $component
        ->set('selectedPatientId', $patient->id)
        ->set('selectedServiceId', $service->id)
        ->set('selectedServicePriceId', $servicePrice->id);

    expect($component->get('selectedPrice'))->toEqual('200');

    $component->call('addService');

    expect($component->get('activeRows'))->toHaveCount(1);
    expect($component->get('activeRows')[0]['service_name'])->toEqual($service->name);
    expect($component->get('activeRows')[0]['price'])->toEqual(200);

    $component->call('confirmAndPrintReceipt')->assertHasNoErrors();

    expect(Visit::where('patient_id', $patient->id)->count())->toBe(1);
    $visit = Visit::where('patient_id', $patient->id)->first();
    expect($visit->status)->toEqual('confirmed');
    expect($visit->shift_id)->not->toBeNull();
    expect($visit->created_by)->toEqual($user->id);

    expect(Invoice::where('visit_id', $visit->id)->count())->toBe(1);
    $invoice = Invoice::where('visit_id', $visit->id)->first();
    expect($invoice->total_amount)->toEqual(200);
    expect($invoice->status)->toEqual('paid');
    expect($invoice->shift_id)->toEqual($visit->shift_id);
    expect($invoice->created_by)->toEqual($user->id);

    expect(InvoiceService::where('invoice_id', $invoice->id)->count())->toBe(1);
    $invSvc = InvoiceService::where('invoice_id', $invoice->id)->first();
    expect($invSvc->price)->toEqual(200);
    expect($invSvc->final_amount)->toEqual(200);

    expect(VisitService::where('visit_id', $visit->id)->count())->toBe(1);
    $visitSvc = VisitService::where('visit_id', $visit->id)->first();
    expect($visitSvc->service_id)->toEqual($service->id);
    expect($visitSvc->doctor_id)->toEqual($doctor->id);

    expect(Queue::where('service_id', $service->id)->where('doctor_id', $doctor->id)->count())->toBe(1);
    $queue = Queue::where('service_id', $service->id)->where('doctor_id', $doctor->id)->first();
    expect($queue->current_token)->toEqual(1);

    expect(QueueToken::where('visit_id', $visit->id)->count())->toBe(1);
    $token = QueueToken::where('visit_id', $visit->id)->first();
    expect($token->token_number)->toEqual(1);
    expect($token->status)->toEqual('waiting');
    expect($token->shift_id)->toEqual($visit->shift_id);
    expect($token->created_by)->toEqual($user->id);
});

test('walk-in confirm fails with open shift first when no shift is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $family = Family::create([
        'phone' => '03084447764',
        'head_id' => null,
    ]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'relation_to_head' => 'self',
    ]);
    $family->update(['head_id' => $patient->id]);

    $service = Service::factory()->create(['is_standalone' => true]);
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'price' => 100,
    ]);

    $component = Livewire::test('pages::reception.walkin')
        ->set('phone', '03084447764')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedServiceId', $service->id)
        ->set('selectedServicePriceId', $servicePrice->id)
        ->set('selectedPrice', '100')
        ->call('addService')
        ->call('confirmAndPrintReceipt');

    $component->assertHasErrors('activeRows');
    expect(Visit::where('patient_id', $patient->id)->count())->toBe(0);
});

test('walk-in with standalone service creates visit and invoice without doctor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $family = Family::create([
        'phone' => '03081112222',
        'head_id' => null,
    ]);
    $patient = Patient::factory()->create(['family_id' => $family->id]);

    $service = Service::factory()->create(['is_standalone' => true]);
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'price' => 150,
    ]);

    Livewire::test('pages::reception.walkin')
        ->set('phone', '03081112222')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedServiceId', $service->id)
        ->set('selectedServicePriceId', $servicePrice->id)
        ->set('selectedPrice', '150')
        ->call('addService')
        ->call('confirmAndPrintReceipt')
        ->assertHasNoErrors();

    $visit = Visit::where('patient_id', $patient->id)->first();
    expect($visit)->not->toBeNull();

    $invoice = Invoice::where('visit_id', $visit->id)->first();
    expect($invoice->total_amount)->toEqual(150);

    $queue = Queue::where('service_id', $service->id)->whereNull('doctor_id')->first();
    expect($queue)->not->toBeNull();

    $token = QueueToken::where('visit_id', $visit->id)->first();
    expect($token->token_number)->toEqual(1);
});

test('add new member when no family exists creates family and patient and sets head when relation is self', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Family::where('phone', '09998887777')->first())->toBeNull();

    Livewire::test('pages::reception.walkin')
        ->set('phone', '09998887777')
        ->call('openNewPatientModal')
        ->set('newPatientName', 'New Member')
        ->set('newPatientGender', 'male')
        ->set('newPatientDob', '1990-01-15')
        ->set('newPatientRelationToHead', 'self')
        ->call('saveNewPatient')
        ->assertHasNoErrors();

    $family = Family::where('phone', '09998887777')->first();
    expect($family)->not->toBeNull();

    $patient = Patient::where('family_id', $family->id)->first();
    expect($patient)->not->toBeNull();
    expect($patient->name)->toEqual('New Member');
    expect($patient->relation_to_head)->toEqual('self');

    expect($family->fresh()->head_id)->toEqual($patient->id);
});

test('getNextTokenFor skips already used tokens', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = Service::factory()->create(['is_standalone' => false]);
    $doctor = Doctor::factory()->create(['status' => 'active']);

    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'queue_type' => 'daily',
        'current_token' => 0,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 1,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);

    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 2,
        'status' => 'waiting',
        'reserved_at' => now(),
    ]);

    Livewire::test('pages::reception.walkin')
        ->call('getNextTokenFor', $service->id, $doctor->id)
        ->assertReturned(3);
});

test('confirmAndPrintReceipt assigns next available token when some already exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $family = Family::create([
        'phone' => '03081112222',
        'head_id' => null,
    ]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'relation_to_head' => 'self',
    ]);
    $family->update(['head_id' => $patient->id]);

    $service = Service::factory()->create(['is_standalone' => false]);
    $doctor = Doctor::factory()->create(['status' => 'active']);
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'price' => 100,
    ]);

    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'queue_type' => 'daily',
        'current_token' => 2,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 1,
        'status' => 'completed',
        'reserved_at' => now(),
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 2,
        'status' => 'waiting',
        'reserved_at' => now(),
    ]);

    Livewire::test('pages::reception.walkin')
        ->set('phone', '03081112222')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedServiceId', $service->id)
        ->set('selectedServicePriceId', $servicePrice->id)
        ->call('addService')
        ->call('confirmAndPrintReceipt')
        ->assertHasNoErrors();

    $visit = Visit::where('patient_id', $patient->id)->latest()->first();
    $token = QueueToken::where('visit_id', $visit->id)->first();
    expect($token->token_number)->toEqual(3);
    expect($queue->fresh()->current_token)->toEqual(3);
});

test('confirm aborts and refreshes tokens when another user took the token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    $family = Family::create([
        'phone' => '03083334444',
        'head_id' => null,
    ]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'relation_to_head' => 'self',
    ]);
    $family->update(['head_id' => $patient->id]);

    $service = Service::factory()->create(['is_standalone' => false]);
    $doctor = Doctor::factory()->create(['status' => 'active']);
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'price' => 50,
    ]);

    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'queue_type' => 'daily',
        'current_token' => 2,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 1,
        'status' => 'completed',
        'reserved_at' => now(),
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 2,
        'status' => 'waiting',
        'reserved_at' => now(),
    ]);

    $component = Livewire::test('pages::reception.walkin')
        ->set('phone', '03083334444')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedServiceId', $service->id)
        ->set('selectedServicePriceId', $servicePrice->id)
        ->call('addService');

    expect($component->get('activeRows')[0]['token_number'])->toEqual(3);

    QueueToken::create([
        'queue_id' => $queue->id,
        'visit_id' => Visit::factory()->create()->id,
        'patient_id' => Patient::factory()->create()->id,
        'token_number' => 3,
        'status' => 'waiting',
        'reserved_at' => now(),
    ]);
    $queue->update(['current_token' => 3]);

    $component->call('confirmAndPrintReceipt');

    $component->assertHasErrors('activeRows');
    expect($component->get('activeRows')[0]['token_number'])->toEqual(4);
    expect(str_contains($component->get('activeRows')[0]['token_display'], '4'))->toBeTrue();
    expect(Visit::where('patient_id', $patient->id)->count())->toBe(0);
});
