<?php

use App\Models\Doctor;
use App\Models\DoctorPayout;
use App\Models\DoctorPayoutLedger;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
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

function createPayoutTestData(array $overrides = []): array
{
    $user = User::factory()->create();
    $shift = Shift::factory()->open()->create(['opened_by' => $user->id]);
    $doctor = Doctor::factory()->create(array_merge(['is_on_payroll' => false, 'payout_duration' => 7], $overrides['doctor'] ?? []));
    $service = Service::factory()->create();
    $servicePrice = ServicePrice::factory()->create(array_merge([
        'doctor_id' => $doctor->id,
        'service_id' => $service->id,
        'price' => 1000,
        'doctor_share' => 300,
        'hospital_share' => 700,
    ], $overrides['service_price'] ?? []));
    $family = Family::create(['phone' => '123456', 'head_id' => null]);
    $patient = Patient::factory()->create(['family_id' => $family->id]);
    $visit = Visit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
    ]);
    $invoice = Invoice::factory()->create(array_merge([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'created_at' => now(),
        'total_amount' => 1000,
    ], $overrides['invoice'] ?? []));
    $invoiceService = InvoiceService::factory()->create(array_merge([
        'invoice_id' => $invoice->id,
        'service_price_id' => $servicePrice->id,
        'price' => 1000,
        'discount' => 0,
        'final_amount' => 1000,
    ], $overrides['invoice_service'] ?? []));

    return compact('user', 'shift', 'doctor', 'service', 'servicePrice', 'patient', 'visit', 'invoice', 'invoiceService');
}

test('payout page requires authentication', function () {
    $response = $this->get(route('reception.payout'));
    $response->assertRedirect(route('login'));
});

test('unpaid lines for doctor exclude already-ledgered invoice services', function () {
    $data = createPayoutTestData();
    $this->actingAs($data['user']);

    $component = Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id);

    expect($component->get('unpaidLines'))->toHaveCount(1);
    expect($component->get('totalShare'))->toEqual(300);

    $payout = DoctorPayout::create([
        'doctor_id' => $data['doctor']->id,
        'amount' => 300,
        'period_from' => now()->subDays(7)->toDateString(),
        'period_to' => now()->toDateString(),
        'shift_id' => $data['shift']->id,
        'paid_by' => $data['user']->id,
    ]);
    DoctorPayoutLedger::create([
        'doctor_payout_id' => $payout->id,
        'invoice_service_id' => $data['invoiceService']->id,
        'share_amount' => 300,
    ]);

    $component->set('selectedDoctorId', null)->set('selectedDoctorId', $data['doctor']->id);
    expect($component->get('unpaidLines'))->toHaveCount(0);
    expect($component->get('totalShare'))->toEqual(0);
});

test('share calculation is prorated by final amount when discounted', function () {
    $data = createPayoutTestData([
        'invoice' => ['total_amount' => 500],
        'invoice_service' => ['price' => 1000, 'discount' => 500, 'final_amount' => 500],
    ]);
    $this->actingAs($data['user']);

    $component = Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id);

    expect($component->get('totalShare'))->toEqual(150);
});

test('pay doctor creates payout and ledger entries with shift and paid_by', function () {
    $data = createPayoutTestData();
    $this->actingAs($data['user']);

    Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id)
        ->call('payDoctor')
        ->assertHasNoErrors()
        ->assertRedirect(route('reception.payout'));

    expect(DoctorPayout::count())->toEqual(1);
    $payout = DoctorPayout::first();
    expect($payout->doctor_id)->toEqual($data['doctor']->id);
    expect($payout->amount)->toEqual(300);
    expect($payout->shift_id)->toEqual($data['shift']->id);
    expect($payout->paid_by)->toEqual($data['user']->id);

    expect(DoctorPayoutLedger::count())->toEqual(1);
    $ledger = DoctorPayoutLedger::first();
    expect($ledger->doctor_payout_id)->toEqual($payout->id);
    expect($ledger->invoice_service_id)->toEqual($data['invoiceService']->id);
    expect($ledger->share_amount)->toEqual(300);
});

test('after payout unpaid lines for that period are empty', function () {
    $data = createPayoutTestData();
    $this->actingAs($data['user']);

    Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id)
        ->call('payDoctor');

    $component = Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id);
    expect($component->get('unpaidLines'))->toHaveCount(0);
    expect($component->get('totalShare'))->toEqual(0);
});

test('cannot pay doctor when no shift is open', function () {
    $data = createPayoutTestData();
    $data['shift']->update(['closed_at' => now(), 'closed_by' => $data['user']->id]);
    $this->actingAs($data['user']);

    Livewire::test('pages::reception.payout')
        ->set('selectedDoctorId', $data['doctor']->id)
        ->call('payDoctor')
        ->assertHasErrors('shift');

    expect(DoctorPayout::count())->toEqual(0);
    expect(DoctorPayoutLedger::count())->toEqual(0);
});

test('doctors for payout only include non-payroll with payout duration', function () {
    $user = User::factory()->create();
    Doctor::factory()->create(['is_on_payroll' => true, 'payout_duration' => null]);
    $shareDoctor = Doctor::factory()->create(['is_on_payroll' => false, 'payout_duration' => 7]);

    $this->actingAs($user);

    $component = Livewire::test('pages::reception.payout');
    $doctors = $component->get('doctorsForPayout');

    expect($doctors->pluck('id')->toArray())->toContain($shareDoctor->id);
    expect($doctors->where('is_on_payroll', true)->count())->toEqual(0);
    expect($doctors->whereNull('payout_duration')->count())->toEqual(0);
});
