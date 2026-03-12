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
        $onPayroll = fake()->boolean();

        return [
            'name' => fake()->name(),
            'specialization' => fake()->word(),
            'phone' => fake()->phoneNumber(),
            'is_on_payroll' => $onPayroll,
            'payout_duration' => $onPayroll ? null : fake()->randomElement([7, 15, 30]),
            'status' => fake()->randomElement(['active', 'left', 'on_leave']),
        ];
    }
}
