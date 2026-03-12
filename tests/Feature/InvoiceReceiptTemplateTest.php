<?php

use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use App\Printing\ReceiptTemplates\InvoiceReceiptTemplate;

test('invoice receipt template includes header and footer', function () {
    $user = User::factory()->create();
    $shift = Shift::factory()->create(['opened_by' => $user->id]);
    $family = \App\Models\Family::create(['phone' => '03001234567', 'head_id' => null]);
    $patient = Patient::factory()->create(['family_id' => $family->id, 'relation_to_head' => 'self']);
    $family->update(['head_id' => $patient->id]);
    $visit = Visit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'confirmed',
    ]);
    $invoice = Invoice::factory()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'paid',
    ]);

    $template = new InvoiceReceiptTemplate($invoice);
    $text = $template->toEscPosText();

    expect($text)->toContain('Mohsin Medical Complex');
    expect($text)->toContain('Invoice');
    expect($text)->toContain('Thanks for coming.');
    expect($text)->toContain('Contact :');
});

test('invoice receipt template includes Rx and blank lines only when doctor_id 1 and service_id 1', function () {
    $user = User::factory()->create();
    $shift = Shift::factory()->create(['opened_by' => $user->id]);
    $family = \App\Models\Family::create(['phone' => '03001234567', 'head_id' => null]);
    $patient = Patient::factory()->create(['family_id' => $family->id, 'relation_to_head' => 'self']);
    $family->update(['head_id' => $patient->id]);
    $visit = Visit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'confirmed',
    ]);

    Service::unguarded(fn () => Service::create(['id' => 1, 'name' => 'Consultation', 'is_standalone' => false]));
    Doctor::unguarded(fn () => Doctor::create(['id' => 1, 'name' => 'Dr Mo', 'specialization' => 'GP', 'phone' => null, 'is_on_payroll' => false, 'payout_duration' => null, 'status' => 'active']));
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => 1,
        'doctor_id' => 1,
        'price' => 1500,
    ]);

    $invoice = Invoice::factory()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'paid',
    ]);

    InvoiceService::create([
        'invoice_id' => $invoice->id,
        'service_price_id' => $servicePrice->id,
        'serviceprice_id' => $servicePrice->id,
        'price' => 1500,
        'discount' => 0,
        'final_amount' => 1500,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
    ]);

    $template = new InvoiceReceiptTemplate($invoice->fresh());
    $text = $template->toEscPosText();

    expect($text)->toContain('bp:                  temp:');
    expect($text)->toContain('Rx:');
});

test('invoice receipt template omits Rx block when not doctor 1 service 1', function () {
    $user = User::factory()->create();
    $shift = Shift::factory()->create(['opened_by' => $user->id]);
    $family = \App\Models\Family::create(['phone' => '03001234567', 'head_id' => null]);
    $patient = Patient::factory()->create(['family_id' => $family->id, 'relation_to_head' => 'self']);
    $family->update(['head_id' => $patient->id]);
    $visit = Visit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'confirmed',
    ]);

    // Use ids other than 1 so the Rx block (doc 1, service 1) is not added
    $service = Service::unguarded(fn () => Service::create(['id' => 2, 'name' => 'X-Ray', 'is_standalone' => false]));
    $doctor = Doctor::unguarded(fn () => Doctor::create(['id' => 2, 'name' => 'Dr Ali', 'specialization' => 'GP', 'phone' => null, 'is_on_payroll' => false, 'payout_duration' => null, 'status' => 'active']));
    $servicePrice = ServicePrice::factory()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'price' => 2000,
    ]);

    $invoice = Invoice::factory()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'status' => 'paid',
    ]);

    InvoiceService::create([
        'invoice_id' => $invoice->id,
        'service_price_id' => $servicePrice->id,
        'serviceprice_id' => $servicePrice->id,
        'price' => 2000,
        'discount' => 0,
        'final_amount' => 2000,
        'shift_id' => $shift->id,
        'created_by' => $user->id,
    ]);

    $template = new InvoiceReceiptTemplate($invoice->fresh());
    $text = $template->toEscPosText();

    expect($text)->toContain('bp:                  temp:');
    expect($text)->not->toContain('Rx:');
});
