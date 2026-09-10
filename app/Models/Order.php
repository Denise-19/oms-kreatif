<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Services\OrderStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Transisi status order ke status baru melalui OrderStateMachine.
     */
    public function transitionTo(OrderStatus $newStatus, ?string $reason = null): self
    {
        app(OrderStateMachine::class)->transition($this, $newStatus, $reason);

        return $this;
    }

    /**
     * Periksa apakah order dapat bertransisi ke status tertentu.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return app(OrderStateMachine::class)->canTransition($this, $newStatus);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
