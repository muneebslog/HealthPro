<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-80 years', '-1 year');

        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement(["male","female"]),
            'age' => (int) now()->diffInYears($dob),
            'dob' => $dob->format('Y-m-d'),
            'relation_to_head' => fake()->word(),
            'family_id' => Family::factory(),
        ];
    }
}
