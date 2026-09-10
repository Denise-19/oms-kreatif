<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Memvalidasi apakah transisi dari status saat ini ke status baru diperbolehkan.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * Mengembalikan daftar status tujuan yang diizinkan dari status saat ini.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::CREATED => [self::PENDING_PAYMENT, self::CANCELLED],
            self::PENDING_PAYMENT => [self::PAID, self::FAILED, self::CANCELLED],
            self::PAID => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::COMPLETED],
            self::FAILED, self::CANCELLED, self::COMPLETED => [],
        };
    }

    /**
     * Menentukan apakah status ini merupakan status akhir (terminal state).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::FAILED, self::CANCELLED, self::COMPLETED => true,
            default => false,
        };
    }

    /**
     * Label yang dapat dibaca manusia.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PAID => 'Paid',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
