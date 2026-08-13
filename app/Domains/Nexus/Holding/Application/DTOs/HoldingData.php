<?php

namespace App\Domains\Nexus\Holding\Application\DTOs;

use App\Domains\Nexus\Business\Domain\Entities\Business;
use App\Domains\Nexus\Holding\Domain\Entities\Holding;
use App\Domains\Nexus\Holding\Domain\Entities\HoldingSubsidiary;

final class HoldingData
{
    /**
     * @param  list<array{id: int, businessId: int, nameEn: string, status: string, invitedAt: string, respondedAt: ?string}>  $subsidiaries
     */
    public function __construct(
        public readonly int $id,
        public readonly int $parentBusinessId,
        public readonly string $parentBusinessNameEn,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly string $status,
        public readonly array $subsidiaries,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param  list<HoldingSubsidiary>  $subsidiaries
     * @param  array<int, Business>  $subsidiaryBusinesses  keyed by businessId
     */
    public static function fromEntity(Holding $holding, Business $parentBusiness, array $subsidiaries, array $subsidiaryBusinesses): self
    {
        return new self(
            id: $holding->id(),
            parentBusinessId: $holding->parentBusinessId(),
            parentBusinessNameEn: $parentBusiness->nameEn(),
            nameFa: $holding->nameFa(),
            nameEn: $holding->nameEn(),
            status: $holding->status()->value,
            subsidiaries: array_map(fn (HoldingSubsidiary $s) => [
                'id' => $s->id(),
                'businessId' => $s->businessId(),
                'nameEn' => $subsidiaryBusinesses[$s->businessId()]?->nameEn() ?? "#{$s->businessId()}",
                'status' => $s->status()->value,
                'invitedAt' => $s->invitedAt()->format(DATE_ATOM),
                'respondedAt' => $s->respondedAt()?->format(DATE_ATOM),
            ], $subsidiaries),
            createdAt: $holding->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parentBusinessId' => $this->parentBusinessId,
            'parentBusinessNameEn' => $this->parentBusinessNameEn,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'status' => $this->status,
            'subsidiaries' => $this->subsidiaries,
            'createdAt' => $this->createdAt,
        ];
    }
}
