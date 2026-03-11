<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'service_id' => Service::factory(),
            'doctor_id' => Doctor::factory(),
            'status' => fake()->randomElement(["assigned","waiting","inprogress","completed"]),
        ];
    }
}
