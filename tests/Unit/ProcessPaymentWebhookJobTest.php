<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessPaymentWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_successful_payment_updates_payment_and_transitions_order_to_paid(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::PENDING,
            'external_reference' => 'PAY-SUCCESS-001',
        ]);

        $payload = [
            'external_reference' => 'PAY-SUCCESS-001',
            'status' => 'success',
            'transaction_id' => 'TRX-112233',
        ];

        $job = new ProcessPaymentWebhookJob($payload);
        $job->handle();

        $this->assertEquals(PaymentStatus::SUCCESS, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::PAID, $order->fresh()->status);
        $this->assertEquals($payload, $payment->fresh()->payload_response);

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order) {
            return $event->order->id === $order->id
                && $event->fromStatus === OrderStatus::PENDING_PAYMENT
                && $event->toStatus === OrderStatus::PAID;
        });
    }

    public function test_process_failed_payment_updates_payment_and_transitions_order_to_failed(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::PENDING,
            'external_reference' => 'PAY-FAILED-002',
        ]);

        $payload = [
            'external_reference' => 'PAY-FAILED-002',
            'status' => 'failed',
            'reason' => 'Insufficient funds in customer account',
        ];

        $job = new ProcessPaymentWebhookJob($payload);
        $job->handle();

        $this->assertEquals(PaymentStatus::FAILED, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::FAILED, $order->fresh()->status);

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order) {
            return $event->order->id === $order->id
                && $event->fromStatus === OrderStatus::PENDING_PAYMENT
                && $event->toStatus === OrderStatus::FAILED
                && $event->reason === 'Insufficient funds in customer account';
        });
    }

    public function test_job_is_idempotent_on_duplicate_webhook_calls(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::SUCCESS,
            'external_reference' => 'PAY-DUPLICATE-003',
        ]);

        $payload = [
            'external_reference' => 'PAY-DUPLICATE-003',
            'status' => 'success',
        ];

        $job = new ProcessPaymentWebhookJob($payload);
        $job->handle();

        $this->assertEquals(PaymentStatus::SUCCESS, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::PAID, $order->fresh()->status);

        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_job_handles_non_existent_reference_gracefully(): void
    {
        $payload = [
            'external_reference' => 'PAY-NON-EXISTENT',
            'status' => 'success',
        ];

        $job = new ProcessPaymentWebhookJob($payload);
        $job->handle();

        $this->assertDatabaseMissing('payments', [
            'external_reference' => 'PAY-NON-EXISTENT',
        ]);
    }
}
