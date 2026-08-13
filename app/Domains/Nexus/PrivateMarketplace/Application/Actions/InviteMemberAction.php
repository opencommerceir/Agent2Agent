<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceMember;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceStatus;
use InvalidArgumentException;

final class InviteMemberAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $marketplaceId, int $callingBusinessId, int $targetBusinessId): void
    {
        $marketplace = $this->marketplaces->findById($marketplaceId);

        if (! $marketplace) {
            throw new InvalidArgumentException("Private Marketplace [{$marketplaceId}] does not exist.");
        }

        if ($marketplace->ownerBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the owning Business may invite members.');
        }

        if ($marketplace->status() !== PrivateMarketplaceStatus::Active) {
            throw new InvalidArgumentException("Private Marketplace [{$marketplaceId}] is not active.");
        }

        if ($targetBusinessId === $marketplace->ownerBusinessId()) {
            throw new InvalidArgumentException('A Private Marketplace cannot invite its own owning Business as a member.');
        }

        if (! $this->businesses->findById($targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] does not exist.");
        }

        if ($this->members->findByMarketplaceAndBusiness($marketplaceId, $targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] has already been invited to this Private Marketplace.");
        }

        $this->members->save(PrivateMarketplaceMember::invite($marketplaceId, $targetBusinessId));
    }
}
