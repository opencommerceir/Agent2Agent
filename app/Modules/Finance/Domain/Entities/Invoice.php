<?php

namespace App\Modules\Finance\Domain\Entities;

use App\Modules\Finance\Domain\ValueObjects\InvoiceNumber;
use App\Modules\Finance\Domain\ValueObjects\InvoiceStatus;
use App\Modules\Finance\Domain\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A billing record generated from a Commerce Order (referenced only by
 * id — orderId — never a direct object reference once persisted; the
 * Order itself is only read at creation time, through Commerce's own
 * OrderRepositoryInterface, to snapshot its items/subtotal — see
 * CreateInvoiceAction). Items are frozen at construction (same Immutable
 * Order Items reasoning Commerce's Order/OrderItem already established).
 */
final class Invoice
{
    /**
     * @param list<InvoiceItem> $items
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $orderId,
        private readonly ?int $customerId,
        private readonly InvoiceNumber $invoiceNumber,
        private InvoiceStatus $status,
        private readonly array $items,
        private readonly Money $subtotal,
        private readonly Money $tax,
        private readonly Money $total,
        private ?DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<InvoiceItem> $items
     */
    public static function create(
        int $tenantId,
        int $orderId,
        ?int $customerId,
        InvoiceNumber $invoiceNumber,
        array $items,
        Money $subtotal,
        Money $tax,
        Money $total,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            orderId: $orderId,
            customerId: $customerId,
            invoiceNumber: $invoiceNumber,
            status: InvoiceStatus::Draft,
            items: $items,
            subtotal: $subtotal,
            tax: $tax,
            total: $total,
            issuedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * The only transition modeled so far (Draft -> Issued) — Paid and
     * Cancelled are real states with no Action requested yet to reach
     * them (InvoiceStatus's own docblock). A plain InvalidArgumentException
     * guard is enough here, the same choice Order::refund() makes for its
     * own simple "wrong state" check, rather than a dedicated exception
     * class for a single transition.
     */
    public function issue(): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw new InvalidArgumentException(
                "Invoice [{$this->invoiceNumber}] cannot be issued from status [{$this->status->value}]."
            );
        }

        $this->status = InvoiceStatus::Issued;
        $this->issuedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function invoiceNumber(): InvoiceNumber
    {
        return $this->invoiceNumber;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    /**
     * @return list<InvoiceItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function tax(): Money
    {
        return $this->tax;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function issuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
