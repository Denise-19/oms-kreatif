<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    public function __construct(
        string $message = 'Stok produk tidak mencukupi untuk memenuhi pesanan.',
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->getCode() ?: 422,
                'message' => $this->getMessage(),
            ],
        ], 422);
    }
}
