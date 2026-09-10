<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ExternalApiException extends Exception
{
    public function __construct(
        string $message = 'Terjadi kesalahan saat memanggil layanan eksternal.',
        int $code = 502,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->getCode() ?: 502,
                'message' => $this->getMessage(),
            ],
        ], $this->getCode() >= 400 && $this->getCode() < 600 ? $this->getCode() : 502);
    }
}
