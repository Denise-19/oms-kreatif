<?php

namespace App\Services;

use App\ApiClients\DummyJsonApiClient;
use App\ApiClients\FakeStoreApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductAggregationService
{
    public function __construct(
        protected FakeStoreApiClient $fakeStoreClient,
        protected DummyJsonApiClient $dummyJsonClient
    ) {}

    public function getAllProducts(): array
    {
        return Cache::remember('aggregated_products', 600, function () {
            $products = [];

            try {
                $fakeStoreProducts = $this->fakeStoreClient->fetchProducts();
                $products = array_merge($products, $fakeStoreProducts);
            } catch (\Throwable $e) {
                Log::error('FakeStoreAPI fetch failed: '.$e->getMessage());
            }

            try {
                $dummyJsonProducts = $this->dummyJsonClient->fetchProducts();
                $products = array_merge($products, $dummyJsonProducts);
            } catch (\Throwable $e) {
                Log::error('DummyJSON fetch failed: '.$e->getMessage());
            }

            return $products;
        });
    }
}
