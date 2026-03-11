<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePriceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'doctor_id' => Doctor::factory(),
            'price' => fake()->numberBetween(-10000, 10000),
            'doctor_share' => fake()->numberBetween(-10000, 10000),
            'hospital_share' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
