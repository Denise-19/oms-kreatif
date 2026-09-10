<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class MockCheckoutController extends Controller
{
    public function show(string $reference): JsonResponse
    {
        $payment = Payment::query()
            ->where('external_reference', $reference)
            ->with('order')
            ->first();

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => "Payment with reference '{$reference}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mock checkout page. Use the webhook_example to simulate payment completion.',
            'data' => [
                'external_reference' => $payment->external_reference,
                'provider' => $payment->provider,
                'status' => $payment->status->value,
                'amount' => (float) $payment->amount,
                'order_number' => $payment->order?->order_number,
                'order_status' => $payment->order?->status->value,
            ],
            'webhook_example' => [
                'url' => url('/api/webhooks/payment'),
                'method' => 'POST',
                'payload_success' => [
                    'external_reference' => $payment->external_reference,
                    'status' => 'success',
                    'transaction_id' => 'TXN-'.strtoupper(substr(md5($reference), 0, 8)),
                    'amount' => (float) $payment->amount,
                ],
                'payload_failed' => [
                    'external_reference' => $payment->external_reference,
                    'status' => 'failed',
                    'reason' => 'Insufficient funds',
                ],
            ],
        ]);
    }
}
