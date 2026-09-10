<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('external_product_id');
            $table->string('source_api'); // 'fakestore' atau 'dummyjson'
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->json('raw_payload')->nullable(); // Simpan response asli API
            $table->integer('stock_quantity')->default(100); // Untuk tes race condition
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_snapshots');
    }
};
