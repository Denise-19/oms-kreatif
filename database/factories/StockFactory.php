<?php

namespace Database\Factories;

use App\Models\ProductSnapshot;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_snapshot_id' => ProductSnapshot::factory(),
            'quantity' => fake()->numberBetween(10, 200),
            'reserved_quantity' => 0,
            'version' => 1,
        ];
    }

    /**
     * State for a product with no available stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
        ]);
    }

    /**
     * State for a specific quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }
}
