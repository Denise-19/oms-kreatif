<?php

namespace App\Services;

use App\Models\ApiCallLog;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public function calculateCost(
        string $origin,
        string $destination,
        int $weightInGrams,
        string $courier = 'jne',
        string $service = 'REG'
    ): float {
        $apiKey = config('services.rajaongkir.api_key', env('RAJAONGKIR_API_KEY'));

        if (! empty($apiKey)) {
            $cost = $this->callRajaOngkir($apiKey, $origin, $destination, $weightInGrams, $courier, $service);
            if ($cost !== null) {
                return $cost;
            }
        }

        // Fallback simulasi kalkulasi jika API Key tidak diset atau layanan eksternal bermasalah
        return $this->calculateFallbackCost($weightInGrams, $courier, $service);
    }

    public function createShipment(Order $order, array $shippingData): Shipment
    {
        $courier = strtolower($shippingData['courier'] ?? 'jne');
        $service = strtoupper($shippingData['service'] ?? 'REG');
        $origin = (string) ($shippingData['origin'] ?? 'Jakarta');
        $destination = (string) ($shippingData['destination'] ?? 'Bandung');
        $weight = (int) ($shippingData['weight'] ?? 1000);

        $cost = $this->calculateCost($origin, $destination, $weight, $courier, $service);

        return Shipment::create([
            'order_id' => $order->id,
            'courier' => $courier,
            'service' => $service,
            'cost' => $cost,
            'status' => 'pending',
            'tracking_number' => null,
        ]);
    }

    /**
     * Memanggil API RajaOngkir dan mencatatnya ke ApiCallLog.
     */
    protected function callRajaOngkir(
        string $apiKey,
        string $origin,
        string $destination,
        int $weight,
        string $courier,
        string $service
    ): ?float {
        $url = 'https://api.rajaongkir.com/starter/cost';
        $payload = [
            'origin' => $origin,
            'destination' => $destination,
            'weight' => $weight,
            'courier' => strtolower($courier),
        ];

        $startTime = microtime(true);
        $statusCode = null;
        $responseBody = null;

        try {
            $response = Http::withoutVerifying()
                ->timeout(5)
                ->withHeaders(['key' => $apiKey])
                ->post($url, $payload);

            $statusCode = $response->status();
            $responseBody = $response->json();

            if ($response->successful()) {
                $results = $responseBody['rajaongkir']['results'][0]['costs'] ?? [];
                foreach ($results as $item) {
                    if (strtoupper($item['service'] ?? '') === strtoupper($service)) {
                        return (float) ($item['cost'][0]['value'] ?? 0);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('RajaOngkir call failed: '.$e->getMessage());
            $responseBody = ['error' => $e->getMessage()];
        } finally {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            ApiCallLog::create([
                'api_name' => 'RajaOngkir',
                'endpoint' => $url,
                'request_payload' => $payload,
                'response_payload' => $responseBody,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
            ]);
        }

        return null;
    }

    /**
     * Fallback kalkulasi ongkir berbasis formula deterministik kurir & berat.
     */
    public function calculateFallbackCost(int $weightInGrams, string $courier, string $service): float
    {
        $kg = max(1, (int) ceil($weightInGrams / 1000));

        $baseRate = match (strtolower($courier)) {
            'pos' => 9000.0,
            'tiki' => 12000.0,
            default => 10000.0, // jne
        };

        $multiplier = match (strtoupper($service)) {
            'YES', 'EXPRESS' => 1.8,
            'OKE', 'ECO' => 0.8,
            default => 1.0, // REG
        };

        return round($baseRate * $kg * $multiplier, 2);
    }
}
