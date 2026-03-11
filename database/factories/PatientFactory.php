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
        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement(["male","female"]),
            'dob' => fake()->date(),
            'relation_to_head' => fake()->word(),
            'family_id' => Family::factory(),
        ];
    }
}
