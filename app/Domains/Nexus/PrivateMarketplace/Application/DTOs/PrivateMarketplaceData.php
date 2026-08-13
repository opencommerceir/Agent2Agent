<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\DTOs;

use App\Domains\Nexus\Business\Domain\Entities\Business;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplace;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceMember;

final class PrivateMarketplaceData
{
    /**
     * @param  list<array{id: int, businessId: int, nameEn: string, status: string}>  $members
     */
    public function __construct(
        public readonly int $id,
        public readonly int $ownerBusinessId,
        public readonly string $ownerBusinessNameEn,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly ?string $brandingPrimaryColor,
        public readonly string $status,
        public readonly array $members,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param  list<PrivateMarketplaceMember>  $members
     * @param  array<int, Business>  $memberBusinesses
     */
    public static function fromEntity(PrivateMarketplace $marketplace, Business $ownerBusiness, array $members, array $memberBusinesses): self
    {
        return new self(
            id: $marketplace->id(),
            ownerBusinessId: $marketplace->ownerBusinessId(),
            ownerBusinessNameEn: $ownerBusiness->nameEn(),
            nameFa: $marketplace->nameFa(),
            nameEn: $marketplace->nameEn(),
            brandingPrimaryColor: $marketplace->brandingPrimaryColor(),
            status: $marketplace->status()->value,
            members: array_map(fn (PrivateMarketplaceMember $m) => [
                'id' => $m->id(),
                'businessId' => $m->businessId(),
                'nameEn' => $memberBusinesses[$m->businessId()]?->nameEn() ?? "#{$m->businessId()}",
                'status' => $m->status()->value,
            ], $members),
            createdAt: $marketplace->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ownerBusinessId' => $this->ownerBusinessId,
            'ownerBusinessNameEn' => $this->ownerBusinessNameEn,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'brandingPrimaryColor' => $this->brandingPrimaryColor,
            'status' => $this->status,
            'members' => $this->members,
            'createdAt' => $this->createdAt,
        ];
    }
}
