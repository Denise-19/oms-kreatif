<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ProductSnapshot;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function deductStock(ProductSnapshot $snapshot, int $quantity, int $maxRetries = 3): void
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            /** @var Stock|null $stock */
            $stock = $snapshot->stock()->first();

            if (! $stock) {
                throw new InsufficientStockException(
                    "Stok untuk produk '{$snapshot->name}' tidak ditemukan."
                );
            }

            if ($stock->quantity < $quantity) {
                throw new InsufficientStockException(
                    "Stok produk '{$snapshot->name}' tidak mencukupi. Tersedia: {$stock->quantity}, diminta: {$quantity}."
                );
            }

            // Attempt an atomic update guarded by the current version.
            $affected = DB::table('stocks')
                ->where('id', $stock->id)
                ->where('version', $stock->version)
                ->where('quantity', '>=', $quantity)
                ->update([
                    'quantity' => $stock->quantity - $quantity,
                    'version' => $stock->version + 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 1) {
                return; // Deduction succeeded.
            }

            // Another process updated the row concurrently — retry.
            $attempts++;
        }

        throw new InsufficientStockException(
            "Gagal memperbarui stok produk '{$snapshot->name}' setelah {$maxRetries} percobaan akibat konflik bersamaan."
        );
    }

    public function restoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $snapshot = $item->productSnapshot;
                if (! $snapshot) {
                    continue;
                }
                $stock = $snapshot->stock;
                if (! $stock) {
                    // If no stock record exists, create one with the cancelled quantity.
                    Stock::create([
                        'product_snapshot_id' => $snapshot->id,
                        'quantity' => $item->quantity,
                        'reserved_quantity' => 0,
                    ]);
                } else {
                    $stock->increment('quantity', $item->quantity);
                }
            }
        });
    }
}
