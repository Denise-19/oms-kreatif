<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductAggregationService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ProductAggregationService $productService): JsonResponse
    {
        $products = $productService->getAllProducts();

        return response()->json([
            'success' => true,
            'total' => count($products),
            'data' => $products,
        ]);
    }
}
