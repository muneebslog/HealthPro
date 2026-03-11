<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->phoneNumber(),
            'head_id' => Patient::factory(),
            'patient_id' => Patient::factory(),
        ];
    }
}
