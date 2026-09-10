<?php

namespace App\ApiClients;

class DummyJsonApiClient extends BaseApiClient
{
    protected string $apiName = 'DummyJSON';

    public function fetchProducts(): array
    {
        $data = $this->get('https://dummyjson.com/products');
        $products = $data['products'] ?? [];

        // Standarisasi format output
        return array_map(function ($item) {
            return [
                'external_id' => (string) $item['id'],
                'source' => 'dummyjson',
                'name' => $item['title'],
                'price' => (float) $item['price'],
                'description' => $item['description'] ?? '',
                'category' => $item['category'] ?? '',
            ];
        }, $products);
    }
}
