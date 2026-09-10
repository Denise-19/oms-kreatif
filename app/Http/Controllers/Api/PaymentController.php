<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentWebhookRequest;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initiate(Order $order, Request $request, PaymentService $paymentService): JsonResponse
    {
        try {
            $provider = $request->input('provider', 'mock_gateway');
            $payment = $paymentService->initiatePayment($order, (string) $provider);

            return response()->json([
                'success' => true,
                'message' => 'Payment request initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'provider' => $payment->provider,
                    'status' => $payment->status->value,
                    'amount' => (float) $payment->amount,
                    'external_reference' => $payment->external_reference,
                    'checkout_url' => url("/api/mock-checkout/{$payment->external_reference}"),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function webhook(PaymentWebhookRequest $request): JsonResponse
    {
        // Melempar pekerjaan ke Redis / async queue tanpa membebani respons HTTP
        ProcessPaymentWebhookJob::dispatch($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Webhook received and queued for asynchronous processing',
        ], 200);
    }
}
