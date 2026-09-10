<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly array $payload,
    ) {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        $externalReference = $this->payload['external_reference'] ?? null;

        if (empty($externalReference)) {
            Log::warning('ProcessPaymentWebhookJob: payload does not contain external_reference', $this->payload);

            return;
        }

        DB::transaction(function () use ($externalReference) {
            $payment = Payment::query()
                ->where('external_reference', $externalReference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning("ProcessPaymentWebhookJob: Payment with reference '{$externalReference}' not found.");

                return;
            }

            // jika sudah diproses sebelumnya, jangan proses ulang
            if ($payment->status !== PaymentStatus::PENDING) {
                Log::info("ProcessPaymentWebhookJob: Payment {$payment->id} already processed with status '{$payment->status->value}'. Skipping duplicate execution.");

                return;
            }

            $statusString = strtolower((string) ($this->payload['status'] ?? ''));
            $isSuccess = $statusString === 'success';

            $payment->status = $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
            $payment->payload_response = $this->payload;
            $payment->save();

            $order = $payment->order;
            if ($order !== null) {
                if ($isSuccess) {
                    $order->transitionTo(OrderStatus::PAID, 'Payment completed via webhook callback');
                } else {
                    $reason = $this->payload['reason'] ?? 'Payment failed via webhook callback';
                    $order->transitionTo(OrderStatus::FAILED, (string) $reason);
                }
            }
        });
    }
}
