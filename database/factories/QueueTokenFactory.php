<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Queue;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'queue_id' => Queue::factory(),
            'visit_id' => Visit::factory(),
            'patient_id' => Patient::factory(),
            'token_number' => fake()->numberBetween(-10000, 10000),
            'status' => fake()->randomElement(["waiting","called","completed","skipped","cancelled"]),
            'reserved_at' => fake()->dateTime(),
            'paid_at' => fake()->dateTime(),
            'called_at' => fake()->dateTime(),
            'completed_at' => fake()->dateTime(),
        ];
    }
}
