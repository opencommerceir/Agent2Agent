<?php

namespace App\Domains\Nexus\Negotiation\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The terms on the table at a given point in a Negotiation — price,
 * quantity, an optional free-text note. Used as-is for both the initial
 * proposal and every counter-offer (docs/nexus-roadmap.md names
 * `Proposal`/`CounterProposal`/`NegotiationTerms` as three separate VOs,
 * but a counter-offer is structurally identical data to a proposal —
 * "new terms at round N" — so one VO covers both rather than two empty
 * wrapper types around the same three fields).
 */
final class NegotiationTerms
{
    public function __construct(
        private readonly Money $price,
        private readonly int $quantity,
        private readonly ?string $notes,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException("NegotiationTerms quantity must be at least 1, got [{$quantity}].");
        }
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function totalAmount(): int
    {
        return $this->price->amount() * $this->quantity;
    }

    /**
     * @return array{priceAmount: int, priceCurrency: string, quantity: int, notes: ?string}
     */
    public function toArray(): array
    {
        return [
            'priceAmount' => $this->price->amount(),
            'priceCurrency' => $this->price->currency(),
            'quantity' => $this->quantity,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            price: Money::fromAmount($data['priceAmount'], $data['priceCurrency']),
            quantity: $data['quantity'],
            notes: $data['notes'] ?? null,
        );
    }
}
