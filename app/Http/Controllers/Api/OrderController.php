<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\ExternalApiException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderStateMachine;
use App\Services\ShippingService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Create a new order
    public function store(CreateOrderRequest $request, OrderService $orderService): JsonResponse
    {
        try {
            $order = $orderService->createOrder($request);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => $this->formatOrder($order),
            ], 201);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ExternalApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Advance an order from PAID → PROCESSING
    public function process(Request $request, Order $order): JsonResponse
    {
        return $this->transitionOrder($order, OrderStatus::PROCESSING, $request->input('reason'));
    }

    // Advance an order from PROCESSING → SHIPPED
    public function ship(Request $request, Order $order, ShippingService $shippingService): JsonResponse
    {
        $trackingNumber = $request->input('tracking_number');

        try {
            app(OrderStateMachine::class)->transition($order, OrderStatus::SHIPPED, $request->input('reason'));

            if ($trackingNumber && $order->shipment) {
                $order->shipment->update([
                    'tracking_number' => $trackingNumber,
                    'status' => 'shipped',
                ]);
            }

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Order marked as shipped.',
                'data' => $this->formatOrder($order),
            ]);
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // Advance an order from SHIPPED → COMPLETED
    public function complete(Request $request, Order $order): JsonResponse
    {
        if ($order->shipment) {
            $order->shipment->update(['status' => 'delivered']);
        }

        return $this->transitionOrder($order, OrderStatus::COMPLETED, $request->input('reason'));
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return response()->json(['message' => 'Order already cancelled.'], 409);
        }

        try {
            app(OrderStateMachine::class)->transition($order, OrderStatus::CANCELLED, $request->input('reason'));

            app(StockService::class)->restoreStock($order);

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data' => $this->formatOrder($order),
            ]);
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // Cancel an order
    public function statusHistory(Order $order): JsonResponse
    {
        $history = $order->statusHistories()
            ->orderBy('created_at')
            ->get(['id', 'order_id', 'old_status', 'new_status', 'reason', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }


    protected function transitionOrder(Order $order, OrderStatus $targetStatus, ?string $reason): JsonResponse
    {
        try {
            app(OrderStateMachine::class)->transition($order, $targetStatus, $reason);

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => "Order transitioned to {$targetStatus->label()}.",
                'data' => $this->formatOrder($order),
            ]);
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }


    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'subtotal' => (float) $order->subtotal,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'idempotency_key' => $order->idempotency_key,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
