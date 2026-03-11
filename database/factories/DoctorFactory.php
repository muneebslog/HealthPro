<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'specialization' => fake()->word(),
            'phone' => fake()->phoneNumber(),
            'is_on_payroll' => fake()->boolean(),
            'status' => fake()->randomElement(["active","left","onleaves"]),
        ];
    }
}
