<?php

namespace App\ApiClients;

use App\Models\ApiCallLog;
use Illuminate\Support\Facades\Http;

abstract class BaseApiClient
{
    protected string $apiName;

    protected function get(string $url, array $query = []): array
    {
        $startTime = microtime(true);
        $statusCode = null;
        $responseBody = null;

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->retry(2, 200)
                ->get($url, $query);

            $statusCode = $response->status();
            $responseBody = $response->json();

            return $responseBody ?? [];
        } catch (\Throwable $e) {
            $responseBody = ['error' => $e->getMessage()];

            return []; // Kembalikan array kosong jika gagal agar fallback bekerja
        } finally {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            ApiCallLog::create([
                'api_name' => $this->apiName,
                'endpoint' => $url,
                'request_payload' => $query,
                'response_payload' => $responseBody,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
            ]);
        }
    }
}
