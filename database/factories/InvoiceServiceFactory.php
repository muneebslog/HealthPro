<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'serviceprice_id' => ServicePrice::factory(),
            'invoice_id' => Invoice::factory(),
            'price' => fake()->numberBetween(-10000, 10000),
            'discount' => fake()->numberBetween(-10000, 10000),
            'final_amount' => fake()->numberBetween(-10000, 10000),
            'service_price_id' => ServicePrice::factory(),
        ];
    }
}
