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

test('reception procedures list page requires authentication', function () {
    $response = $this->get(route('reception.procedures'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception procedures list page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.procedures'));
    $response->assertOk();
});

test('procedures list shows procedure admissions', function () {
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

    $admission = ProcedureAdmission::create([
        'patient_id' => $patient->id,
        'package_name' => 'Cataract Surgery',
        'full_price' => 50000,
        'operation_doctor_id' => null,
        'operation_date' => null,
        'room' => 'Room 1',
        'bed' => 'Bed 2',
        'shift_id' => Shift::current()->id,
        'created_by' => $user->id,
    ]);

    Invoice::create([
        'patient_id' => $patient->id,
        'visit_id' => null,
        'procedure_admission_id' => $admission->id,
        'total_amount' => 50000,
        'paid_amount' => 20000,
        'status' => 'partialpaid',
        'shift_id' => Shift::current()->id,
        'created_by' => $user->id,
    ]);

    $component = Livewire::test('pages::reception.procedures');

    $component->assertSee('Cataract Surgery');
    $component->assertSee($patient->name);
    $component->assertSee('50,000');
    $component->assertSee('20,000');
    $component->assertSee('30,000');
    $component->assertSee('Add Payment');
});

test('add payment from procedures list updates invoice', function () {
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

    $admission = ProcedureAdmission::create([
        'patient_id' => $patient->id,
        'package_name' => 'Test Package',
        'full_price' => 50000,
        'shift_id' => Shift::current()->id,
        'created_by' => $user->id,
    ]);

    $invoice = Invoice::create([
        'patient_id' => $patient->id,
        'visit_id' => null,
        'procedure_admission_id' => $admission->id,
        'total_amount' => 50000,
        'paid_amount' => 20000,
        'status' => 'partialpaid',
        'shift_id' => Shift::current()->id,
        'created_by' => $user->id,
    ]);

    expect($invoice->remainingBalance())->toEqual(30000);

    Livewire::test('pages::reception.procedures')
        ->call('openAddPaymentModal', $invoice->id)
        ->set('addPaymentAmount', 30000)
        ->call('saveAddPayment');

    $invoice->refresh();
    expect($invoice->paid_amount)->toEqual(50000);
    expect($invoice->status)->toEqual('paid');
    expect($invoice->remainingBalance())->toEqual(0);
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
});
