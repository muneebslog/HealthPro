<?php

use App\Models\Family;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

test('MR lookup page requires authentication', function () {
    $response = $this->get(route('reception.mr-lookup'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit MR lookup page and see search', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('reception.mr-lookup'));
    $response->assertOk();
    $response->assertSee(__('MR Lookup'), false);
    $response->assertSee(__('MR number'), false);
});

test('search with valid MR shows patient summary visits and invoices', function () {
    $this->actingAs(User::factory()->create());

    $family = Family::create(['phone' => '03001112222', 'head_id' => null]);
    $patient = Patient::factory()->create([
        'family_id' => $family->id,
        'mr_number' => 'MR-001234',
    ]);
    $family->update(['head_id' => $patient->id]);

    $visit = Visit::factory()->create(['patient_id' => $patient->id, 'status' => 'completed']);
    Invoice::factory()->create([
        'patient_id' => $patient->id,
        'visit_id' => $visit->id,
        'total_amount' => 500,
        'status' => 'paid',
    ]);

    Livewire::test('pages::reception.mr-lookup')
        ->set('mrSearch', 'MR-001234')
        ->assertSee($patient->name)
        ->assertSee('MR-001234')
        ->assertSee(__('Patient summary'), false)
        ->assertSee(__('Visits'), false)
        ->assertSee(__('Invoices'), false)
        ->assertSee('500');
});

test('search with digits only finds patient', function () {
    $this->actingAs(User::factory()->create());

    $patient = Patient::factory()->create(['mr_number' => 'MR-005678']);

    Livewire::test('pages::reception.mr-lookup')
        ->set('mrSearch', '5678')
        ->assertSee($patient->name);
});

test('search with invalid MR shows not found message', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::reception.mr-lookup')
        ->set('mrSearch', 'MR-999999')
        ->assertSee(__('No patient found for this MR number.'), false);
});
