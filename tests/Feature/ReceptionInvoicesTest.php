<?php

use App\Models\User;

test('reception invoices page requires authentication', function () {
    $response = $this->get(route('reception.invoices'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception invoices page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.invoices'));
    $response->assertOk();
});

test('invoices page shows table and search', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('reception.invoices'));
    $response->assertOk();
    $response->assertSee(__('Invoices'), false);
    $response->assertSee(__('Search by patient, doctor or service'), false);
    $response->assertSee(__('Patient'), false);
    $response->assertSee(__('Services'), false);
});
