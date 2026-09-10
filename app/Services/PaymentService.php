<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function initiatePayment(Order $order, string $provider = 'mock_gateway'): Payment
    {
        if ($order->status === OrderStatus::CREATED) {
            $order->transitionTo(OrderStatus::PENDING_PAYMENT, 'Payment initiated');
        }

        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            throw new RuntimeException(
                sprintf("Order dengan status '%s' tidak dapat diproses pembayarannya.", $order->status->value)
            );
        }

        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::PENDING)
            ->first();

        if ($existingPayment !== null) {
            return $existingPayment;
        }

        $externalReference = sprintf(
            'PAY-%s-%s',
            $order->order_number,
            strtoupper(Str::random(6))
        );

        return Payment::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total,
            'external_reference' => $externalReference,
            'payload_response' => null,
        ]);
    }
}
