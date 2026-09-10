<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_endpoint_returns_immediate_200_and_pushes_job_to_queue(): void
    {
        Queue::fake();

        $payload = [
            'external_reference' => 'PAY-TEST-12345',
            'status' => 'success',
            'transaction_id' => 'TRX-PG-999',
            'amount' => 150000.00,
        ];

        $response = $this->postJson(route('payments.webhook'), $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook received and queued for asynchronous processing',
            ]);

        Queue::assertPushed(ProcessPaymentWebhookJob::class, function (ProcessPaymentWebhookJob $job) use ($payload) {
            return $job->payload['external_reference'] === $payload['external_reference']
                && $job->payload['status'] === 'success'
                && $job->queue === 'payments';
        });
    }

    public function test_webhook_validation_requires_external_reference_and_status(): void
    {
        Queue::fake();

        $response = $this->postJson(route('payments.webhook'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['external_reference', 'status']);

        Queue::assertNothingPushed();
    }

    public function test_webhook_validation_rejects_invalid_status(): void
    {
        Queue::fake();

        $response = $this->postJson(route('payments.webhook'), [
            'external_reference' => 'PAY-TEST-12345',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        Queue::assertNothingPushed();
    }
}
