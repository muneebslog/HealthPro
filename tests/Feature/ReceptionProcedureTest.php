<?php

use App\Models\Family;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProcedureAdmission;
use App\Models\Shift;
use App\Models\User;
use App\Printing\ReceiptPrinterConnectorFactory;
use Livewire\Livewire;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

beforeEach(function () {
    $factoryMock = \Mockery::mock(ReceiptPrinterConnectorFactory::class);
    $factoryMock->shouldReceive('printer')->with(\Mockery::type('string'))
        ->andReturnUsing(function () {
            $connector = new DummyPrintConnector;

            return new Printer($connector);
        });

    $this->app->instance(ReceiptPrinterConnectorFactory::class, $factoryMock);
});

test('reception procedure page requires authentication', function () {
    $response = $this->get(route('reception.procedure'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception procedure page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.procedure'));
    $response->assertOk();
});

test('procedure flow creates procedure admission invoice and payment when confirming', function () {
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

    $component = Livewire::test('pages::reception.procedure')
        ->set('phone', '03084447764')
        ->set('selectedPatientId', $patient->id)
        ->set('packageName', 'Cataract Surgery Package')
        ->set('fullPrice', 50000)
        ->set('advancePayment', 20000);

    $component->call('confirmAndPrintReceipt')->assertHasNoErrors();

    expect(ProcedureAdmission::where('patient_id', $patient->id)->count())->toBe(1);
    $admission = ProcedureAdmission::where('patient_id', $patient->id)->first();
    expect($admission->package_name)->toEqual('Cataract Surgery Package');
    expect($admission->full_price)->toEqual(50000);
    expect($admission->shift_id)->not->toBeNull();
    expect($admission->created_by)->toEqual($user->id);

    expect(Invoice::where('procedure_admission_id', $admission->id)->count())->toBe(1);
    $invoice = Invoice::where('procedure_admission_id', $admission->id)->first();
    expect($invoice->total_amount)->toEqual(50000);
    expect($invoice->paid_amount)->toEqual(20000);
    expect($invoice->status)->toEqual('partialpaid');
    expect($invoice->visit_id)->toBeNull();

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
    $payment = Payment::where('invoice_id', $invoice->id)->first();
    expect($payment->amount)->toEqual(20000);
});

test('procedure page requires open shift', function () {
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

    $component = Livewire::test('pages::reception.procedure')
        ->set('phone', '03084447764')
        ->set('selectedPatientId', $patient->id)
        ->set('packageName', 'Test Package')
        ->set('fullPrice', 10000)
        ->set('advancePayment', 5000);

    $component->call('confirmAndPrintReceipt')->assertHasErrors();
});

test('add payment updates invoice paid amount and status', function () {
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

    $component = Livewire::test('pages::reception.procedure')
        ->set('phone', '03084447764')
        ->set('selectedPatientId', $patient->id)
        ->set('packageName', 'Test Package')
        ->set('fullPrice', 50000)
        ->set('advancePayment', 20000);

    $component->call('confirmAndPrintReceipt')->assertHasNoErrors();

    $invoice = Invoice::where('procedure_admission_id', ProcedureAdmission::where('patient_id', $patient->id)->first()->id)->first();
    expect($invoice->paid_amount)->toEqual(20000);
    expect($invoice->remainingBalance())->toEqual(30000);

    Livewire::test('pages::reception.invoices')
        ->call('openAddPaymentModal', $invoice->id)
        ->set('addPaymentAmount', 30000)
        ->call('saveAddPayment');

    $invoice->refresh();
    expect($invoice->paid_amount)->toEqual(50000);
    expect($invoice->status)->toEqual('paid');
    expect($invoice->remainingBalance())->toEqual(0);
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(2);
});
