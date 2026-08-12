<?php

namespace App\Domains\Nexus\Growth\Domain\Entities;

use App\Domains\Nexus\Growth\Domain\Exceptions\InvalidCoalitionStateException;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The roadmap's "Group Buying": Agents of several Businesses pool orders
 * for one catalog item from one supplier to unlock a bulk discount. Reuses
 * Negotiation's own CatalogItemType/Money VOs directly rather than copying
 * them a fourth time — unlike Catalog's/Negotiation's/Credit's independent
 * Money copies (each guards a value that's genuinely theirs alone), a
 * Coalition's whole purpose is to culminate in exactly one real Negotiation
 * (CloseCoalitionAction), so its price is always headed straight into a
 * NegotiationTerms — a separate copy would just be converted straight back.
 * Framework-free (Domain Layer Rules).
 *
 * State machine: same explicit ALLOWED_TRANSITIONS + guarded transitionTo()
 * shape every other Nexus aggregate (Negotiation/Escrow/CreditPurchaseSession)
 * already uses. The discount actually landing is never guaranteed here —
 * `close()` only proposes the bulk deal (via InitiateNegotiationAction); the
 * target supplier's own Agent still has to accept/counter/reject it like
 * any other Negotiation, same honesty Escrow's own docblock already applies
 * to "state-tracking, not a real settlement."
 */
final class Coalition
{
    /**
     * @var array<string, list<CoalitionStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'forming' => [CoalitionStatus::Negotiating, CoalitionStatus::Cancelled],
        'negotiating' => [CoalitionStatus::Completed, CoalitionStatus::Cancelled],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $organizerBusinessId,
        private readonly int $targetBusinessId,
        private readonly CatalogItemType $catalogItemType,
        private readonly int $catalogItemId,
        private readonly Money $unitPrice,
        private readonly int $minParticipants,
        private readonly float $discountPercent,
        private CoalitionStatus $status,
        private ?int $negotiationId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function form(
        int $organizerBusinessId,
        int $targetBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        Money $unitPrice,
        int $minParticipants,
        float $discountPercent,
    ): self {
        if ($organizerBusinessId === $targetBusinessId) {
            throw new InvalidArgumentException('A Business cannot organize a coalition against itself.');
        }

        if ($minParticipants < 2) {
            throw new InvalidArgumentException("minParticipants must be at least 2, got [{$minParticipants}].");
        }

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException("discountPercent must be between 0 and 100, got [{$discountPercent}].");
        }

        return new self(
            id: null,
            organizerBusinessId: $organizerBusinessId,
            targetBusinessId: $targetBusinessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            unitPrice: $unitPrice,
            minParticipants: $minParticipants,
            discountPercent: $discountPercent,
            status: CoalitionStatus::Forming,
            negotiationId: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function startNegotiating(int $negotiationId): void
    {
        $this->transitionTo(CoalitionStatus::Negotiating);
        $this->negotiationId = $negotiationId;
    }

    public function complete(): void
    {
        $this->transitionTo(CoalitionStatus::Completed);
    }

    public function cancel(): void
    {
        $this->transitionTo(CoalitionStatus::Cancelled);
    }

    /**
     * The price actually proposed to the target supplier once the coalition
     * closes — discountPercent applied to the per-unit price agreed when
     * the coalition was formed.
     */
    public function discountedUnitPrice(): Money
    {
        $discounted = (int) round($this->unitPrice->amount() * (1 - $this->discountPercent / 100));

        return Money::fromAmount($discounted, $this->unitPrice->currency());
    }

    private function transitionTo(CoalitionStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidCoalitionStateException(
                "Coalition cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function organizerBusinessId(): int
    {
        return $this->organizerBusinessId;
    }

    public function targetBusinessId(): int
    {
        return $this->targetBusinessId;
    }

    public function catalogItemType(): CatalogItemType
    {
        return $this->catalogItemType;
    }

    public function catalogItemId(): int
    {
        return $this->catalogItemId;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function minParticipants(): int
    {
        return $this->minParticipants;
    }

    public function discountPercent(): float
    {
        return $this->discountPercent;
    }

    public function status(): CoalitionStatus
    {
        return $this->status;
    }

    public function negotiationId(): ?int
    {
        return $this->negotiationId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
