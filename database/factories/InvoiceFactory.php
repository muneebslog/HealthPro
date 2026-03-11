<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'visit_id' => Visit::factory(),
            'total_amount' => fake()->numberBetween(-10000, 10000),
            'status' => fake()->randomElement(["unpaid","paid","partialpaid"]),
        ];
    }
}
