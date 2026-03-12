<?php

use App\Models\Doctor;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('reception queues page requires authentication', function () {
    $response = $this->get(route('reception.queues'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can visit reception queues page', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('reception.queues'));
    $response->assertOk();
});

test('queues page shows current queues by default', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create(['name' => 'Checkup']);
    $doctor = Doctor::factory()->create(['name' => 'Dr. Test', 'status' => 'active']);
    $activeQueue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'queue_type' => 'daily',
        'current_token' => 2,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $response = $this->get(route('reception.queues'));
    $response->assertOk();
    $response->assertSee(__('Queues'), false);
    $response->assertSee(__('Current queues'), false);
    $response->assertSee(__('Older queues'), false);
    $response->assertSee('Checkup', false);
    $response->assertSee('Dr. Test', false);
});

test('switching to older queues shows discontinued queues', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create(['name' => 'Old Service']);
    $doctor = Doctor::factory()->create(['name' => 'Dr. Old', 'status' => 'active']);
    Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'queue_type' => 'shift',
        'current_token' => 10,
        'status' => 'discontinued',
        'started_at' => now()->subDay(),
        'ended_at' => now()->subHours(2),
    ]);

    $component = Livewire::test('pages::reception.queues')
        ->assertSet('showOlder', false)
        ->set('showOlder', true);

    $component->assertSet('showOlder', true);
    $component->assertSee('Old Service', false);
    $component->assertSee('Dr. Old', false);
});
