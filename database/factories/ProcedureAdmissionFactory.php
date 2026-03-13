<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcedureAdmission>
 */
class ProcedureAdmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'package_name' => fake()->words(3, true),
            'full_price' => fake()->numberBetween(10000, 500000),
            'operation_doctor_id' => Doctor::factory(),
            'operation_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'room' => 'Room'.fake()->numberBetween(1, 20),
            'bed' => 'Bed '.fake()->numberBetween(1, 10),
            'shift_id' => Shift::factory(),
            'created_by' => User::factory(),
        ];
    }
}
