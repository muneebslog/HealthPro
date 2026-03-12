<?php

use App\Actions\PrintReceipt;
use App\Models\Doctor;
use App\Models\DoctorPayout;
use App\Models\Invoice;
use App\Models\ReceiptPrint;
use App\Models\Shift;
use App\Models\User;
use App\Printing\ReceiptPrinterConnectorFactory;

beforeEach(function () {
    $printerMock = \Mockery::mock();
    $printerMock->shouldReceive('setJustification')->andReturnSelf();
    $printerMock->shouldReceive('setTextSize')->andReturnSelf();
    $printerMock->shouldReceive('setEmphasis')->andReturnSelf();
    $printerMock->shouldReceive('text')->with(\Mockery::type('string'))->andReturnSelf();
    $printerMock->shouldReceive('cut')->andReturnSelf();
    $printerMock->shouldReceive('close')->andReturnNull();

    $factoryMock = \Mockery::mock(ReceiptPrinterConnectorFactory::class);
    $factoryMock->shouldReceive('printer')->with(\Mockery::type('string'))->andReturn($printerMock);

    $this->app->instance(ReceiptPrinterConnectorFactory::class, $factoryMock);
});

test('forInvoice creates receipt print record and sends to printer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shift = Shift::factory()->create(['opened_by' => $user->id, 'closed_at' => null]);
    $family = \App\Models\Family::create(['phone' => '03001234567', 'head_id' => null]);
    $patient = \App\Models\Patient::factory()->create(['family_id' => $family->id, 'relation_to_head' => 'self']);
    $family->update(['head_id' => $patient->id]);
    $visit = \App\Models\Visit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'confirmed',
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'paid',
    ]);

    $countBefore = ReceiptPrint::count();

    app(PrintReceipt::class)->forInvoice($invoice);

    expect(ReceiptPrint::count())->toBe($countBefore + 1);
    $print = ReceiptPrint::latest()->first();
    expect($print->print_type)->toBe('invoice');
    expect($print->invoice_id)->toBe($invoice->id);
    expect($print->printed_by)->toBe($user->id);
});

test('forShiftClose creates receipt print record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shift = Shift::factory()->create([
        'opened_by' => $user->id,
        'closed_at' => now(),
        'closed_by' => $user->id,
        'expected_cash' => 1000,
    ]);

    $countBefore = ReceiptPrint::count();

    app(PrintReceipt::class)->forShiftClose($shift);

    expect(ReceiptPrint::count())->toBe($countBefore + 1);
    $print = ReceiptPrint::latest()->first();
    expect($print->print_type)->toBe('shift_close');
    expect($print->shift_id)->toBe($shift->id);
});

test('forDoctorPayout creates receipt print record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $doctor = Doctor::factory()->create();
    $shift = Shift::factory()->create(['opened_by' => $user->id]);
    $payout = DoctorPayout::create([
        'doctor_id' => $doctor->id,
        'amount' => 5000,
        'period_from' => now()->subDays(7),
        'period_to' => now(),
        'shift_id' => $shift->id,
        'paid_by' => $user->id,
    ]);
    $payout->setRelation('doctor', $doctor);

    $countBefore = ReceiptPrint::count();

    app(PrintReceipt::class)->forDoctorPayout($payout);

    expect(ReceiptPrint::count())->toBe($countBefore + 1);
    $print = ReceiptPrint::latest()->first();
    expect($print->print_type)->toBe('doctor_payout');
    expect($print->doctor_payout_id)->toBe($payout->id);
});
