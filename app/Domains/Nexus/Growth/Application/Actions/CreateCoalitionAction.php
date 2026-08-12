<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Entities\Coalition;
use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Organizer forms a Coalition and is immediately its first Member — their
 * own committed quantity counts toward the bulk order like everyone else's
 * (Coalition entity's own docblock).
 */
final class CreateCoalitionAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $organizerBusinessId,
        int $targetBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $unitPriceAmount,
        string $unitPriceCurrency,
        int $minParticipants,
        float $discountPercent,
        int $organizerQuantity,
    ): CoalitionData {
        if (! $this->businesses->findById($targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] does not exist.");
        }

        $this->costGate->execute($organizerBusinessId, 'nexus.coalition.create');

        $coalition = $this->coalitions->save(Coalition::form(
            organizerBusinessId: $organizerBusinessId,
            targetBusinessId: $targetBusinessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            unitPrice: Money::fromAmount($unitPriceAmount, $unitPriceCurrency),
            minParticipants: $minParticipants,
            discountPercent: $discountPercent,
        ));

        $member = $this->members->save(CoalitionMember::join($coalition->id(), $organizerBusinessId, $organizerQuantity));

        return CoalitionData::fromEntity($coalition, [$member]);
    }
}
