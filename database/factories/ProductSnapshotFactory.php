<?php

namespace Database\Factories;

use App\Models\ProductSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSnapshot>
 */
class ProductSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceApis = ['fakestore', 'dummyjson'];

        return [
            'external_product_id' => (string) fake()->unique()->numberBetween(1, 9999),
            'source_api' => fake()->randomElement($sourceApis),
            'name' => fake()->words(3, true),
            'price' => fake()->randomFloat(2, 5, 500),
            'raw_payload' => null,
            'stock_quantity' => 100,
        ];
    }

    /**
     * State for a FakeStore product.
     */
    public function fakestore(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_api' => 'fakestore',
        ]);
    }

    /**
     * State for a DummyJSON product.
     */
    public function dummyjson(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_api' => 'dummyjson',
        ]);
    }
}
