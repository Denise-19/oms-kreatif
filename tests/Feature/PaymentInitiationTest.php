<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_initiate_payment_for_created_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CREATED,
            'total' => 250000.00,
        ]);

        $response = $this->postJson(route('payments.initiate', $order), [
            'provider' => 'mock_gateway',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment request initiated successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'provider' => 'mock_gateway',
                    'status' => PaymentStatus::PENDING->value,
                    'amount' => 250000.00,
                ],
            ]);

        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'mock_gateway',
            'status' => PaymentStatus::PENDING->value,
            'amount' => 250000.00,
        ]);
    }

    public function test_can_reinitiate_and_reuses_existing_pending_payment(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
            'total' => 150000.00,
        ]);

        $existingPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::PENDING,
            'amount' => 150000.00,
            'external_reference' => 'PAY-EXISTING-12345',
        ]);

        $response = $this->postJson(route('payments.initiate', $order));

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_id', $existingPayment->id)
            ->assertJsonPath('data.external_reference', 'PAY-EXISTING-12345');

        $this->assertCount(1, Payment::where('order_id', $order->id)->get());
    }

    public function test_cannot_initiate_payment_for_completed_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->postJson(route('payments.initiate', $order));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
