<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Exception;

class InvalidOrderTransitionException extends Exception
{
    public function __construct(
        public readonly OrderStatus $fromStatus,
        public readonly OrderStatus $toStatus,
        string $message = '',
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        if ($message === '') {
            $message = sprintf(
                "Tidak dapat mengubah status order dari '%s' ke '%s'.",
                $this->fromStatus->value,
                $this->toStatus->value
            );
        }

        parent::__construct($message, $code, $previous);
    }

    public static function forTransition(OrderStatus $from, OrderStatus $to): self
    {
        return new self($from, $to);
    }
}
