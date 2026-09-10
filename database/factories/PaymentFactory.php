<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
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
            'order_id' => Order::factory(),
            'provider' => 'mock_gateway',
            'status' => PaymentStatus::PENDING,
            'amount' => fake()->randomFloat(2, 50, 500),
            'external_reference' => 'PAY-'.strtoupper(fake()->bothify('????-#####')),
            'payload_response' => null,
        ];
    }

    public function status(PaymentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
