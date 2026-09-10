<?php

namespace Database\Seeders;

use App\Models\ProductSnapshot;
use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat sample ProductSnapshot sesuai struktur kolom database
        if (ProductSnapshot::count() === 0) {
            ProductSnapshot::create([
                'external_product_id' => '1',
                'source_api'          => 'fakestore',
                'name'                => 'Fjallraven - Foldsack No. 1 Backpack',
                'price'               => 109.95,
                'raw_payload'         => json_encode([
                    'description' => 'Your perfect pack for everyday use and walks in the forest',
                    'category'    => "men's clothing",
                ]),
                'stock_quantity'      => 100,
            ]);
        }

        // 2. Isi data ke tabel stocks
        foreach (ProductSnapshot::all() as $snapshot) {
            Stock::firstOrCreate(
                ['product_snapshot_id' => $snapshot->id],
                ['quantity' => 100, 'reserved_quantity' => 0]
            );
        }
    }
}