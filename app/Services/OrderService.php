<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\ExternalApiException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Models\ProductSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected StockService $stockService,
        protected ShippingService $shippingService,
        protected ProductAggregationService $productAggregationService,
    ) {}

    public function createOrder(CreateOrderRequest $request): Order
    {
        $idempotencyKey = $request->getIdempotencyKey();

        // Return the existing order if the same idempotency key was already used.
        $existing = Order::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $requestItems = $request->validated('items', []);
        $shippingData = $request->validated('shipping', []);

        return DB::transaction(function () use ($request, $idempotencyKey, $requestItems, $shippingData) {
            $subtotal = 0.0;
            $resolvedItems = [];

            foreach ($requestItems as $item) {
                $snapshot = $this->resolveSnapshot(
                    (string) $item['product_id'],
                    (string) $item['source_api'],
                    isset($item['name']) ? (string) $item['name'] : null,
                    isset($item['price']) ? (float) $item['price'] : null,
                );

                $quantity = (int) $item['quantity'];

                // Deduct stock — throws InsufficientStockException if unavailable.
                $this->stockService->deductStock($snapshot, $quantity);

                $priceAtOrder = (float) $snapshot->price;
                $subtotal += $priceAtOrder * $quantity;

                $resolvedItems[] = [
                    'snapshot' => $snapshot,
                    'quantity' => $quantity,
                    'price_at_order' => $priceAtOrder,
                ];
            }

            // Calculate shipping cost upfront so it can be included in the order total.
            $shippingCost = $this->shippingService->calculateCost(
                origin: (string) ($shippingData['origin'] ?? 'Jakarta'),
                destination: (string) ($shippingData['destination'] ?? 'Bandung'),
                weightInGrams: (int) ($shippingData['weight'] ?? 1000),
                courier: (string) ($shippingData['courier'] ?? 'jne'),
                service: (string) ($shippingData['service'] ?? 'REG'),
            );

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $request->validated('user_id'),
                'status' => OrderStatus::CREATED,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $subtotal + $shippingCost,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($resolvedItems as $resolvedItem) {
                $order->items()->create([
                    'product_snapshot_id' => $resolvedItem['snapshot']->id,
                    'quantity' => $resolvedItem['quantity'],
                    'price_at_order' => $resolvedItem['price_at_order'],
                ]);
            }

            // Persist shipment record with the calculated cost.
            $this->shippingService->createShipment($order, $shippingData);

            return $order->load(['items.productSnapshot', 'shipment']);
        });
    }

    protected function resolveSnapshot(
        string $externalProductId,
        string $sourceApi,
        ?string $name,
        ?float $price,
    ): ProductSnapshot {
        $snapshot = ProductSnapshot::query()
            ->where('external_product_id', $externalProductId)
            ->where('source_api', $sourceApi)
            ->first();

        if ($snapshot !== null) {
            return $snapshot;
        }

        // Try to find the product in the aggregated (cached) product list first.
        if ($name === null || $price === null) {
            $products = $this->productAggregationService->getAllProducts();
            $found = collect($products)->first(
                fn (array $p) => (string) ($p['external_id'] ?? '') === $externalProductId
                    && ($p['source'] ?? '') === $sourceApi
            );

            if ($found === null) {
                throw new ExternalApiException(
                    "Produk dengan ID '{$externalProductId}' dari sumber '{$sourceApi}' tidak ditemukan."
                );
            }

            $name = (string) ($found['name'] ?? '');
            $price = (float) ($found['price'] ?? 0);
            $rawPayload = $found;
        }

        $snapshot = ProductSnapshot::create([
            'external_product_id' => $externalProductId,
            'source_api' => $sourceApi,
            'name' => $name,
            'price' => $price,
            'raw_payload' => $rawPayload ?? null,
            'stock_quantity' => 100, // Legacy column — actual stock lives in `stocks` table.
        ]);

        // Initialise a stock record for the newly created snapshot.
        $snapshot->stock()->create([
            'quantity' => 100,
            'reserved_quantity' => 0,
            'version' => 1,
        ]);

        return $snapshot;
    }

    protected function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.strtoupper(Str::random(4)).'-'.now()->format('Ymd');
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
