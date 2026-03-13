<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\DoctorPayout;
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

test('doctor portal routes require authentication', function () {
    $this->get(route('doctor.dashboard'))->assertRedirect(route('login'));
    $this->get(route('doctor.profile'))->assertRedirect(route('login'));
    $this->get(route('doctor.invoices'))->assertRedirect(route('login'));
    $this->get(route('doctor.payouts'))->assertRedirect(route('login'));
    $this->get(route('doctor.appointments'))->assertRedirect(route('login'));
});

test('staff user cannot access doctor portal', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($user);

    $this->get(route('doctor.dashboard'))->assertForbidden();
    $this->get(route('doctor.profile'))->assertForbidden();
    $this->get(route('doctor.invoices'))->assertForbidden();
    $this->get(route('doctor.payouts'))->assertForbidden();
    $this->get(route('doctor.appointments'))->assertForbidden();
});

test('user with doctor role but no linked doctor cannot access doctor portal', function () {
    $user = User::factory()->doctor()->create();
    $this->actingAs($user);

    $this->get(route('doctor.dashboard'))->assertForbidden();
    $this->get(route('doctor.profile'))->assertForbidden();
});

test('user with doctor role and linked inactive doctor cannot access doctor portal', function () {
    $user = User::factory()->doctor()->create();
    Doctor::factory()->create(['user_id' => $user->id, 'status' => 'left']);
    $this->actingAs($user);

    $this->get(route('doctor.dashboard'))->assertForbidden();
    $this->get(route('doctor.profile'))->assertForbidden();
});

test('user with doctor role and linked active doctor can access doctor portal', function () {
    $user = User::factory()->doctor()->create();
    Doctor::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $this->actingAs($user);

    $this->get(route('doctor.dashboard'))->assertSuccessful();
    $this->get(route('doctor.profile'))->assertSuccessful();
    $this->get(route('doctor.invoices'))->assertSuccessful();
    $this->get(route('doctor.payouts'))->assertSuccessful();
    $this->get(route('doctor.appointments'))->assertSuccessful();
});

test('doctor invoices page only shows invoice lines for that doctor', function () {
    $userA = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->create(['user_id' => $userA->id, 'status' => 'active']);
    $doctorB = Doctor::factory()->create(['status' => 'active']);

    $service = Service::create(['name' => 'Consultation', 'is_standalone' => true]);
    $priceA = ServicePrice::create([
        'doctor_id' => $doctorA->id,
        'service_id' => $service->id,
        'price' => 1000,
        'doctor_share' => 400,
        'hospital_share' => 600,
    ]);
    $priceB = ServicePrice::create([
        'doctor_id' => $doctorB->id,
        'service_id' => $service->id,
        'price' => 2000,
        'doctor_share' => 800,
        'hospital_share' => 1200,
    ]);

    $family = Family::create(['phone' => '1234567890', 'head_id' => null]);
    $patient = Patient::create([
        'name' => 'Test Patient',
        'gender' => 'male',
        'dob' => '1990-01-01',
        'relation_to_head' => 'self',
        'family_id' => $family->id,
    ]);
    $visit = Visit::create(['patient_id' => $patient->id, 'status' => 'completed']);
    $invoice = Invoice::create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'total_amount' => 3000,
        'status' => 'paid',
    ]);

    InvoiceService::create([
        'invoice_id' => $invoice->id,
        'service_price_id' => $priceA->id,
        'serviceprice_id' => $priceA->id,
        'price' => 1000,
        'discount' => 0,
        'final_amount' => 1000,
    ]);
    InvoiceService::create([
        'invoice_id' => $invoice->id,
        'service_price_id' => $priceB->id,
        'serviceprice_id' => $priceB->id,
        'price' => 2000,
        'discount' => 0,
        'final_amount' => 2000,
    ]);

    $this->actingAs($userA);
    $response = $this->get(route('doctor.invoices'));
    $response->assertSuccessful();
    $response->assertSee('Consultation');
    $response->assertSee('1,000');
    $response->assertDontSee('2,000');
});

test('doctor payouts page only shows payouts for that doctor', function () {
    $userA = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->create(['user_id' => $userA->id, 'status' => 'active']);
    $doctorB = Doctor::factory()->create(['status' => 'active']);
    $paidBy = User::factory()->create();
    $shift = Shift::factory()->create();

    DoctorPayout::create([
        'doctor_id' => $doctorA->id,
        'amount' => 5000,
        'period_from' => now()->subDays(7),
        'period_to' => now(),
        'shift_id' => $shift->id,
        'paid_by' => $paidBy->id,
    ]);
    DoctorPayout::create([
        'doctor_id' => $doctorB->id,
        'amount' => 10000,
        'period_from' => now()->subDays(7),
        'period_to' => now(),
        'shift_id' => $shift->id,
        'paid_by' => $paidBy->id,
    ]);

    $this->actingAs($userA);
    $response = $this->get(route('doctor.payouts'));
    $response->assertSuccessful();
    $response->assertSee('5,000');
    $response->assertDontSee('10,000');
});

test('doctor appointments page only shows queue for that doctor', function () {
    $userA = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->create(['user_id' => $userA->id, 'status' => 'active']);
    $doctorB = Doctor::factory()->create(['status' => 'active']);

    $service = Service::query()->find(Queue::APPOINTMENT_SERVICE_ID)
        ?? Service::forceCreate(['id' => Queue::APPOINTMENT_SERVICE_ID, 'name' => 'OPD', 'is_standalone' => true]);
    $queueA = Queue::create([
        'doctor_id' => $doctorA->id,
        'service_id' => $service->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);
    $queueB = Queue::create([
        'doctor_id' => $doctorB->id,
        'service_id' => $service->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $familyA = Family::create(['phone' => '1111111111', 'head_id' => null]);
    $familyB = Family::create(['phone' => '2222222222', 'head_id' => null]);
    $patientA = Patient::create([
        'name' => 'Patient For Doctor A',
        'gender' => 'male',
        'dob' => '1990-01-01',
        'relation_to_head' => 'self',
        'family_id' => $familyA->id,
    ]);
    $patientB = Patient::create([
        'name' => 'Patient For Doctor B',
        'gender' => 'female',
        'dob' => '1985-06-15',
        'relation_to_head' => 'self',
        'family_id' => $familyB->id,
    ]);
    $visitA = Visit::create(['patient_id' => $patientA->id, 'status' => 'reserved']);
    $visitB = Visit::create(['patient_id' => $patientB->id, 'status' => 'reserved']);
    QueueToken::create([
        'queue_id' => $queueA->id,
        'visit_id' => $visitA->id,
        'patient_id' => $patientA->id,
        'token_number' => 1,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);
    QueueToken::create([
        'queue_id' => $queueB->id,
        'visit_id' => $visitB->id,
        'patient_id' => $patientB->id,
        'token_number' => 1,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);

    $this->actingAs($userA);
    $response = $this->get(route('doctor.appointments'));
    $response->assertSuccessful();
    $response->assertSee('Patient For Doctor A');
    $response->assertDontSee('Patient For Doctor B');
});
