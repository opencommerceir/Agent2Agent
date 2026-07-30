<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\Exceptions\InvalidOrderStatusException;
use App\Modules\Commerce\Domain\Exceptions\OrderAlreadyCancelledException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use DateTimeImmutable;

/**
 * A placed Order. Items are frozen at construction (Immutable Order
 * Items rule — OrderItem's own docblock); everything mutable about an
 * Order is its status.
 *
 * changeStatus() deliberately refuses to target Cancelled or Refunded:
 * both have their own dedicated side effects (restoring Inventory) that
 * a generic status update must not bypass — CancelOrderAction /
 * Order::cancel() is the only sanctioned path to Cancelled. It also
 * refuses to change status *from* a terminal state (Cancelled, Refunded,
 * Delivered) at all — there is no "un-deliver".
 */
final class Order
{
    private const TERMINAL_STATUSES = [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Delivered];

    private const CANCELLABLE_STATUSES = [OrderStatus::Pending, OrderStatus::Confirmed];

    /**
     * @param list<OrderItem> $items
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $agentId,
        private readonly OrderNumber $orderNumber,
        private OrderStatus $status,
        private readonly array $items,
        private readonly Money $subtotal,
        private readonly Money $total,
        private readonly ?string $notes,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function place(
        int $tenantId,
        int $agentId,
        OrderNumber $orderNumber,
        array $items,
        Money $subtotal,
        Money $total,
        ?string $notes = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            agentId: $agentId,
            orderNumber: $orderNumber,
            status: OrderStatus::Pending,
            items: $items,
            subtotal: $subtotal,
            total: $total,
            notes: $notes,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * No external payment/fraud-check step exists yet in this phase — a
     * future PaymentGateway integration would gate this instead of
     * PlaceOrderAction calling it unconditionally right after place().
     */
    public function confirm(): void
    {
        $this->status = OrderStatus::Confirmed;
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::Cancelled) {
            throw new OrderAlreadyCancelledException("Order [{$this->orderNumber}] is already cancelled.");
        }

        if (! in_array($this->status, self::CANCELLABLE_STATUSES, true)) {
            throw new InvalidOrderStatusException(
                "Order [{$this->orderNumber}] cannot be cancelled from status [{$this->status->value}]."
            );
        }

        $this->status = OrderStatus::Cancelled;
    }

    public function changeStatus(OrderStatus $newStatus): void
    {
        if (in_array($this->status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidOrderStatusException(
                "Order [{$this->orderNumber}] is in a terminal status [{$this->status->value}] and cannot be changed."
            );
        }

        if (in_array($newStatus, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            throw new InvalidOrderStatusException(
                "Use CancelOrderAction to cancel an order; a generic status update cannot target [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function agentId(): int
    {
        return $this->agentId;
    }

    public function orderNumber(): OrderNumber
    {
        return $this->orderNumber;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }
}
