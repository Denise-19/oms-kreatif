<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private OrderStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new OrderStateMachine;
    }

    public function test_can_transition_through_full_happy_path(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::CREATED,
        ]);

        $this->stateMachine->transition($order, OrderStatus::PENDING_PAYMENT);
        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);

        $this->stateMachine->transition($order, OrderStatus::PAID);
        $this->assertEquals(OrderStatus::PAID, $order->fresh()->status);

        $this->stateMachine->transition($order, OrderStatus::PROCESSING);
        $this->assertEquals(OrderStatus::PROCESSING, $order->fresh()->status);

        $this->stateMachine->transition($order, OrderStatus::SHIPPED);
        $this->assertEquals(OrderStatus::SHIPPED, $order->fresh()->status);

        $this->stateMachine->transition($order, OrderStatus::COMPLETED);
        $this->assertEquals(OrderStatus::COMPLETED, $order->fresh()->status);

        Event::assertDispatched(OrderStatusChanged::class, 5);
    }

    public function test_cannot_jump_from_created_directly_to_shipped(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CREATED,
        ]);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->expectExceptionMessage("Tidak dapat mengubah status order dari 'created' ke 'shipped'.");

        $this->stateMachine->transition($order, OrderStatus::SHIPPED);
    }

    public function test_cannot_jump_from_created_directly_to_paid(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CREATED,
        ]);

        $this->expectException(InvalidOrderTransitionException::class);

        $this->stateMachine->transition($order, OrderStatus::PAID);
    }

    public function test_cannot_transition_from_terminal_completed_state(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->assertTrue(OrderStatus::COMPLETED->isTerminal());

        $this->expectException(InvalidOrderTransitionException::class);

        $this->stateMachine->transition($order, OrderStatus::CANCELLED);
    }

    public function test_cannot_transition_from_terminal_cancelled_state(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CANCELLED,
        ]);

        $this->assertTrue(OrderStatus::CANCELLED->isTerminal());

        $this->expectException(InvalidOrderTransitionException::class);

        $this->stateMachine->transition($order, OrderStatus::PENDING_PAYMENT);
    }

    public function test_cannot_transition_from_terminal_failed_state(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::FAILED,
        ]);

        $this->assertTrue(OrderStatus::FAILED->isTerminal());

        $this->expectException(InvalidOrderTransitionException::class);

        $this->stateMachine->transition($order, OrderStatus::PAID);
    }

    public function test_can_fail_from_pending_payment(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $order->transitionTo(OrderStatus::FAILED, 'Payment gateway timeout');

        $this->assertEquals(OrderStatus::FAILED, $order->fresh()->status);

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order) {
            return $event->order->id === $order->id
                && $event->fromStatus === OrderStatus::PENDING_PAYMENT
                && $event->toStatus === OrderStatus::FAILED
                && $event->reason === 'Payment gateway timeout';
        });
    }

    public function test_can_cancel_from_cancellable_states(): void
    {
        $cancellableStates = [
            OrderStatus::CREATED,
            OrderStatus::PENDING_PAYMENT,
            OrderStatus::PAID,
            OrderStatus::PROCESSING,
        ];

        foreach ($cancellableStates as $state) {
            $order = Order::factory()->create(['status' => $state]);
            $this->assertTrue($order->canTransitionTo(OrderStatus::CANCELLED));
            $order->transitionTo(OrderStatus::CANCELLED, 'Cancelled by user');
            $this->assertEquals(OrderStatus::CANCELLED, $order->fresh()->status);
        }
    }

    public function test_model_helper_transition_to_updates_and_dispatches_event(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::CREATED,
        ]);

        $this->assertTrue($order->canTransitionTo(OrderStatus::PENDING_PAYMENT));
        $this->assertFalse($order->canTransitionTo(OrderStatus::SHIPPED));

        $order->transitionTo(OrderStatus::PENDING_PAYMENT);

        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order) {
            return $event->order->id === $order->id
                && $event->fromStatus === OrderStatus::CREATED
                && $event->toStatus === OrderStatus::PENDING_PAYMENT;
        });
    }
}
