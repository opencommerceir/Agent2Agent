<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Entities;

use App\Domains\Nexus\PrivateMarketplace\Domain\Exceptions\InvalidPrivateMarketplaceMemberStateException;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceMemberStatus;
use DateTimeImmutable;

final class PrivateMarketplaceMember
{
    /**
     * @var array<string, list<PrivateMarketplaceMemberStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'invited' => [PrivateMarketplaceMemberStatus::Active, PrivateMarketplaceMemberStatus::Removed],
        'active' => [PrivateMarketplaceMemberStatus::Removed],
        'removed' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $privateMarketplaceId,
        private readonly int $businessId,
        private PrivateMarketplaceMemberStatus $status,
        private readonly DateTimeImmutable $invitedAt,
    ) {
    }

    public static function invite(int $privateMarketplaceId, int $businessId): self
    {
        return new self(
            id: null,
            privateMarketplaceId: $privateMarketplaceId,
            businessId: $businessId,
            status: PrivateMarketplaceMemberStatus::Invited,
            invitedAt: new DateTimeImmutable(),
        );
    }

    public function accept(): void
    {
        $this->transitionTo(PrivateMarketplaceMemberStatus::Active);
    }

    public function remove(): void
    {
        $this->transitionTo(PrivateMarketplaceMemberStatus::Removed);
    }

    private function transitionTo(PrivateMarketplaceMemberStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidPrivateMarketplaceMemberStateException(
                "PrivateMarketplaceMember cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function privateMarketplaceId(): int
    {
        return $this->privateMarketplaceId;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function status(): PrivateMarketplaceMemberStatus
    {
        return $this->status;
    }

    public function invitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }
}
