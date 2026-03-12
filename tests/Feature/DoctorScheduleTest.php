<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;

test('doctor can have schedule slots', function () {
    $doctor = Doctor::factory()->create(['status' => 'active']);

    $doctor->schedules()->createMany([
        ['day' => 'monday', 'start_time' => '09:00', 'end_time' => '17:00'],
        ['day' => 'wednesday', 'start_time' => '10:00', 'end_time' => '14:00'],
    ]);

    $doctor->load('schedules');
    expect($doctor->schedules)->toHaveCount(2);
    expect($doctor->schedules->first()->day)->toBe('monday');
    expect($doctor->schedules->first()->start_time)->toBe('09:00');
    expect($doctor->schedules->first()->end_time)->toBe('17:00');
});

test('deleting doctor cascades to schedules', function () {
    $doctor = Doctor::factory()->create(['status' => 'active']);
    $doctor->schedules()->create(['day' => 'friday', 'start_time' => '08:00', 'end_time' => '16:00']);

    $doctorId = $doctor->id;
    $doctor->delete();

    expect(DoctorSchedule::where('doctor_id', $doctorId)->count())->toBe(0);
});
