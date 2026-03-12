<?php

use App\Models\Doctor;
use App\Models\Queue;
use App\Models\Service;

test('queues close daily appointment command closes active queues for service 1 and doctor id greater than 1', function () {
    Service::factory()->create();
    Doctor::factory()->create();
    $doctor2 = Doctor::factory()->create();
    $doctor3 = Doctor::factory()->create();

    $queueToClose1 = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor2->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);
    $queueToClose2 = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor3->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $this->artisan('queues:close-daily-appointment')
        ->assertSuccessful();

    $queueToClose1->refresh();
    $queueToClose2->refresh();
    expect($queueToClose1->status)->toEqual('discontinued');
    expect($queueToClose1->ended_at)->not->toBeNull();
    expect($queueToClose2->status)->toEqual('discontinued');
    expect($queueToClose2->ended_at)->not->toBeNull();
});

test('queues close daily appointment command does not close queues for service 1 doctor id 1 or other services', function () {
    Service::factory()->create();
    $otherService = Service::factory()->create();
    $doctor1 = Doctor::factory()->create();
    $doctor2 = Doctor::factory()->create();

    $appointmentDoctor1Queue = Queue::create([
        'service_id' => Queue::APPOINTMENT_SERVICE_ID,
        'doctor_id' => $doctor1->id,
        'queue_type' => 'shift',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);
    $otherServiceQueue = Queue::create([
        'service_id' => $otherService->id,
        'doctor_id' => $doctor2->id,
        'queue_type' => 'daily',
        'current_token' => 1,
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $this->artisan('queues:close-daily-appointment')
        ->assertSuccessful();

    $appointmentDoctor1Queue->refresh();
    $otherServiceQueue->refresh();
    expect($appointmentDoctor1Queue->status)->toEqual('active');
    expect($appointmentDoctor1Queue->ended_at)->toBeNull();
    expect($otherServiceQueue->status)->toEqual('active');
    expect($otherServiceQueue->ended_at)->toBeNull();
});
