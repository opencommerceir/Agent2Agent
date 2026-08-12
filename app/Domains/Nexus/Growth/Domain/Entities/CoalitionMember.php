<?php

namespace App\Domains\Nexus\Growth\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row per participating Business (unique per coalition_id+business_id)
 * — including the organizer, who is added as the first member the moment
 * CreateCoalitionAction forms the Coalition (their own committed quantity
 * counts toward the bulk order exactly like everyone else's). Framework-free
 * (Domain Layer Rules).
 */
final class CoalitionMember
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $coalitionId,
        private readonly int $businessId,
        private readonly int $quantity,
        private readonly DateTimeImmutable $joinedAt,
    ) {
    }

    public static function join(int $coalitionId, int $businessId, int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("CoalitionMember quantity must be at least 1, got [{$quantity}].");
        }

        return new self(
            id: null,
            coalitionId: $coalitionId,
            businessId: $businessId,
            quantity: $quantity,
            joinedAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function coalitionId(): int
    {
        return $this->coalitionId;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function joinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
