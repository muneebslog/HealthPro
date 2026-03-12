<?php

use App\Models\ServicePrice;

test('calculated doctor share amount returns stored doctor_share', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 1000,
        'doctor_share' => 400,
        'hospital_share' => 600,
    ]);

    expect($sp->getCalculatedDoctorShareAmount())->toBe(400);
});

test('calculated hospital share amount returns stored hospital_share', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 1000,
        'doctor_share' => 350,
        'hospital_share' => 650,
    ]);

    expect($sp->getCalculatedHospitalShareAmount())->toBe(650);
});

test('doctor share percentage is calculated from price and doctor_share', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 1000,
        'doctor_share' => 300,
        'hospital_share' => 700,
    ]);

    expect($sp->getDoctorSharePercentage())->toBe(30.0);
});

test('hospital share percentage is calculated from price and hospital_share', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 1000,
        'doctor_share' => 250,
        'hospital_share' => 750,
    ]);

    expect($sp->getHospitalSharePercentage())->toBe(75.0);
});

test('doctor share percentage is null when doctor_share is null', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 1000,
        'doctor_share' => null,
        'hospital_share' => 1000,
    ]);

    expect($sp->getDoctorSharePercentage())->toBeNull();
});

test('hospital share percentage is zero when price is zero', function () {
    $sp = ServicePrice::factory()->create([
        'price' => 0,
        'doctor_share' => null,
        'hospital_share' => 0,
    ]);

    expect($sp->getHospitalSharePercentage())->toBe(0.0);
});
