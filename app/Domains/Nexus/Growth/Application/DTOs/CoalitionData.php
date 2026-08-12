<?php

namespace App\Domains\Nexus\Growth\Application\DTOs;

use App\Domains\Nexus\Growth\Domain\Entities\Coalition;
use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember;

final class CoalitionData
{
    /**
     * @param  list<array{businessId: int, quantity: int, joinedAt: string}>  $members
     */
    public function __construct(
        public readonly int $id,
        public readonly int $organizerBusinessId,
        public readonly int $targetBusinessId,
        public readonly string $catalogItemType,
        public readonly int $catalogItemId,
        public readonly int $unitPriceAmount,
        public readonly string $unitPriceCurrency,
        public readonly int $minParticipants,
        public readonly float $discountPercent,
        public readonly string $status,
        public readonly ?int $negotiationId,
        public readonly int $totalQuantity,
        public readonly array $members,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param  list<CoalitionMember>  $members
     */
    public static function fromEntity(Coalition $coalition, array $members): self
    {
        return new self(
            id: $coalition->id(),
            organizerBusinessId: $coalition->organizerBusinessId(),
            targetBusinessId: $coalition->targetBusinessId(),
            catalogItemType: $coalition->catalogItemType()->value,
            catalogItemId: $coalition->catalogItemId(),
            unitPriceAmount: $coalition->unitPrice()->amount(),
            unitPriceCurrency: $coalition->unitPrice()->currency(),
            minParticipants: $coalition->minParticipants(),
            discountPercent: $coalition->discountPercent(),
            status: $coalition->status()->value,
            negotiationId: $coalition->negotiationId(),
            totalQuantity: array_sum(array_map(fn (CoalitionMember $m) => $m->quantity(), $members)),
            members: array_map(fn (CoalitionMember $m) => [
                'businessId' => $m->businessId(),
                'quantity' => $m->quantity(),
                'joinedAt' => $m->joinedAt()->format(DATE_ATOM),
            ], $members),
            createdAt: $coalition->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizerBusinessId' => $this->organizerBusinessId,
            'targetBusinessId' => $this->targetBusinessId,
            'catalogItemType' => $this->catalogItemType,
            'catalogItemId' => $this->catalogItemId,
            'unitPriceAmount' => $this->unitPriceAmount,
            'unitPriceCurrency' => $this->unitPriceCurrency,
            'minParticipants' => $this->minParticipants,
            'discountPercent' => $this->discountPercent,
            'status' => $this->status,
            'negotiationId' => $this->negotiationId,
            'totalQuantity' => $this->totalQuantity,
            'members' => $this->members,
            'createdAt' => $this->createdAt,
        ];
    }
}
