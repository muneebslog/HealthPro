<?php

use App\Models\Doctor;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
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

test('shift page requires authentication', function () {
    $response = $this->get(route('reception.shift'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can open shift with opening cash', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Shift::current())->toBeNull();

    Livewire::test('pages::reception.shift')
        ->set('openingCash', '150.00')
        ->call('openShift')
        ->assertHasNoErrors();

    $shift = Shift::current();
    expect($shift)->not->toBeNull();
    expect($shift->opened_by)->toEqual($user->id);
    expect($shift->opened_at)->not->toBeNull();
    expect((float) $shift->opening_cash)->toEqual(150.0);
    expect($shift->closed_at)->toBeNull();
    expect($shift->closed_by)->toBeNull();
});

test('authenticated user can close open shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shift = Shift::factory()->open()->create(['opened_by' => $user->id]);
    expect(Shift::current()->id)->toEqual($shift->id);

    Livewire::test('pages::reception.shift')
        ->call('closeShift')
        ->assertHasNoErrors();

    $shift->refresh();
    expect($shift->closed_at)->not->toBeNull();
    expect($shift->closed_by)->toEqual($user->id);
    expect(Shift::current())->toBeNull();
});

test('closing shift closes all active queues for that shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Service::factory()->create();
    $doctor1 = Doctor::factory()->create();
    $doctor2 = Doctor::factory()->create();

    $shift = Shift::factory()->open()->create(['opened_by' => $user->id]);
    $otherShift = Shift::factory()->open()->create();

    $queueToClose1 = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor1->id,
        'queue_type' => 'shift',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
    ]);
    $queueToClose2 = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor2->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
    ]);
    $queueOnOtherShift = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor1->id,
        'queue_type' => 'shift',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
        'shift_id' => $otherShift->id,
        'created_by' => $user->id,
    ]);

    Livewire::test('pages::reception.shift')
        ->call('closeShift')
        ->assertHasNoErrors();

    $queueToClose1->refresh();
    $queueToClose2->refresh();
    $queueOnOtherShift->refresh();
    expect($queueToClose1->status)->toEqual('discontinued');
    expect($queueToClose1->ended_at)->not->toBeNull();
    expect($queueToClose2->status)->toEqual('discontinued');
    expect($queueToClose2->ended_at)->not->toBeNull();
    expect($queueOnOtherShift->status)->toEqual('active');
    expect($queueOnOtherShift->ended_at)->toBeNull();
});

test('opening shift validates opening cash', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::reception.shift')
        ->set('openingCash', '-10')
        ->call('openShift')
        ->assertHasErrors('openingCash');

    expect(Shift::current())->toBeNull();
});

test('cannot open shift when one is already open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    Livewire::test('pages::reception.shift')
        ->set('openingCash', '0')
        ->call('openShift')
        ->assertHasErrors('shift');
});

test('cannot close shift when none is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Shift::current())->toBeNull();

    Livewire::test('pages::reception.shift')
        ->call('closeShift')
        ->assertHasErrors('shift');
});

test('authenticated user can log expense when shift is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shift = Shift::factory()->open()->create(['opened_by' => $user->id]);
    expect(Shift::current()->id)->toEqual($shift->id);

    Livewire::test('pages::reception.shift')
        ->set('expenseAmount', '25.50')
        ->set('expenseDescription', 'Petty cash')
        ->call('addExpense')
        ->assertHasNoErrors();

    $shift->refresh();
    expect($shift->expenses)->toHaveCount(1);
    $expense = $shift->expenses->first();
    expect((float) $expense->amount)->toEqual(25.50);
    expect($expense->description)->toEqual('Petty cash');
    expect($expense->recorded_by)->toEqual($user->id);
});

test('cannot add expense when no shift is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Shift::current())->toBeNull();

    Livewire::test('pages::reception.shift')
        ->set('expenseAmount', '10.00')
        ->set('expenseDescription', 'Test')
        ->call('addExpense')
        ->assertHasErrors('shift');

    expect(ShiftExpense::count())->toEqual(0);
});

test('expense validation requires amount and description', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Shift::factory()->open()->create(['opened_by' => $user->id]);

    Livewire::test('pages::reception.shift')
        ->set('expenseAmount', '')
        ->set('expenseDescription', '')
        ->call('addExpense')
        ->assertHasErrors(['expenseAmount', 'expenseDescription']);

    Livewire::test('pages::reception.shift')
        ->set('expenseAmount', '0')
        ->set('expenseDescription', 'Valid')
        ->call('addExpense')
        ->assertHasErrors('expenseAmount');
});
