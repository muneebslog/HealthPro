<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'paid_at' => now(),
            'shift_id' => Shift::factory(),
            'created_by' => User::factory(),
        ];
    }
}
