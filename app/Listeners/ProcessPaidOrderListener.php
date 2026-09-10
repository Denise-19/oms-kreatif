<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Log;

class ProcessPaidOrderListener
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->toStatus === OrderStatus::PAID) {
            Log::info(sprintf(
                'ProcessPaidOrderListener: Order #%s (%s) has been successfully PAID. Transitioning to PROCESSING.',
                $event->order->id,
                $event->order->order_number,
            ));

            $order = $event->order->fresh();

            if ($order === null) {
                Log::error("ProcessPaidOrderListener: Order #{$event->order->id} not found when attempting PROCESSING transition.");

                return;
            }

            if ($order->status !== OrderStatus::PAID) {
                Log::info("ProcessPaidOrderListener: Order #{$order->id} already in '{$order->status->value}', skipping PROCESSING transition.");

                return;
            }

            $order->transitionTo(
                OrderStatus::PROCESSING,
                'Auto-transitioned to processing after successful payment',
            );
        }

        if ($event->toStatus === OrderStatus::FAILED) {
            Log::warning(sprintf(
                'ProcessPaidOrderListener: Order #%s (%s) payment FAILED. Reason: %s',
                $event->order->id,
                $event->order->order_number,
                $event->reason ?? 'Unknown',
            ));
        }
    }
}
