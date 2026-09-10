<?php

namespace App\ApiClients;

class FakeStoreApiClient extends BaseApiClient
{
    protected string $apiName = 'FakeStoreAPI';

    public function fetchProducts(): array
    {
        $data = $this->get('https://fakestoreapi.com/products');

        return array_map(function ($item) {
            return [
                'external_id' => (string) $item['id'],
                'source' => 'fakestore',
                'name' => $item['title'],
                'price' => (float) $item['price'],
                'description' => $item['description'] ?? '',
                'category' => $item['category'] ?? '',
            ];
        }, $data);
    }
}
