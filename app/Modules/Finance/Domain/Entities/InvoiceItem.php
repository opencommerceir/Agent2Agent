<?php

namespace App\Modules\Finance\Domain\Entities;

use App\Modules\Finance\Domain\ValueObjects\Money;

/**
 * A frozen line item on an Invoice — no mutators (mirrors Commerce's
 * Immutable Order Items rule for OrderItem). No `id`/`invoiceId`
 * property on the Domain Entity, even though the `invoice_items` table
 * has an `id` primary key column — nothing ever looks one up by its own
 * id, only ever as part of its parent Invoice, the same "no id field on
 * the Domain entity" shape OrderItem/Discount already established
 * (HANDOFF gotcha #10). description is a plain string snapshot (usually
 * the source Product's name at invoice-creation time via Commerce's
 * ProductRepositoryInterface — see CreateInvoiceAction) rather than a
 * product reference, since an Invoice must remain readable even if the
 * Product it billed is later renamed or deleted.
 */
final class InvoiceItem
{
    private function __construct(
        private readonly string $description,
        private readonly int $quantity,
        private readonly Money $unitPrice,
        private readonly Money $totalAmount,
    ) {
    }

    public static function create(string $description, int $quantity, Money $unitPrice, Money $totalAmount): self
    {
        return new self($description, $quantity, $unitPrice, $totalAmount);
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }
}
