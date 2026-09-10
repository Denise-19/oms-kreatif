<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{

    public function canTransition(Order $order, OrderStatus $targetStatus): bool
    {
        $currentStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);

        return $currentStatus->canTransitionTo($targetStatus);
    }

    public function transition(Order $order, OrderStatus $targetStatus, ?string $reason = null): Order
    {
        $currentStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidOrderTransitionException::forTransition($currentStatus, $targetStatus);
        }

        return DB::transaction(function () use ($order, $currentStatus, $targetStatus, $reason) {
            $order->status = $targetStatus;
            $order->save();

            // Persistently record every status change for auditing.
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $currentStatus->value,
                'new_status' => $targetStatus->value,
                'reason' => $reason,
            ]);

            OrderStatusChanged::dispatch($order, $currentStatus, $targetStatus, $reason);

            return $order;
        });
    }
}
