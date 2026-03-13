<?php

use App\Models\Patient;

test('creating a patient assigns a unique mr_number', function () {
    $patient = Patient::factory()->create();

    expect($patient->mr_number)->not->toBeNull();
    expect($patient->mr_number)->toMatch('/^MR-\d{6}$/');
});

test('generateMrNumber returns unique values', function () {
    $seen = [];
    for ($i = 0; $i < 10; $i++) {
        $mr = Patient::generateMrNumber();
        expect($seen)->not->toContain($mr);
        $seen[] = $mr;
    }
});

test('findByMrNumber finds patient by full MR number', function () {
    $patient = Patient::factory()->create();
    $mr = $patient->mr_number;

    expect(Patient::findByMrNumber($mr))->not->toBeNull();
    expect(Patient::findByMrNumber($mr)->id)->toBe($patient->id);
});

test('findByMrNumber finds patient by digits only', function () {
    $patient = Patient::factory()->create(['mr_number' => 'MR-004291']);

    expect(Patient::findByMrNumber('4291')->id)->toBe($patient->id);
    expect(Patient::findByMrNumber('004291')->id)->toBe($patient->id);
});

test('findByMrNumber returns null for empty or unknown MR', function () {
    expect(Patient::findByMrNumber(''))->toBeNull();
    expect(Patient::findByMrNumber('  '))->toBeNull();
    expect(Patient::findByMrNumber('MR-999999'))->toBeNull();
});

test('patient assign-mr-numbers command assigns MR to patients with null mr_number', function () {
    $p1 = Patient::factory()->create();
    $p2 = Patient::factory()->create();

    \Illuminate\Support\Facades\DB::table('patients')
        ->whereIn('id', [$p1->id, $p2->id])
        ->update(['mr_number' => null]);

    $this->artisan('patient:assign-mr-numbers')->assertSuccessful();

    expect(Patient::find($p1->id)->mr_number)->not->toBeNull();
    expect(Patient::find($p2->id)->mr_number)->not->toBeNull();
    expect(Patient::find($p1->id)->mr_number)->not->toEqual(Patient::find($p2->id)->mr_number);
});
