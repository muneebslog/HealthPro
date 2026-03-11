<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'doctor_id' => Doctor::factory(),
            'queue_type' => fake()->randomElement(["continuous","daily","shift"]),
            'current_token' => fake()->numberBetween(-10000, 10000),
            'status' => fake()->randomElement(["active","discontinuted"]),
            'started_at' => fake()->dateTime(),
            'ended_at' => fake()->dateTime(),
        ];
    }
}
