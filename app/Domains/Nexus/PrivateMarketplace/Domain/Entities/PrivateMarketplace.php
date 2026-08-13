<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Entities;

use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Phase 7/M5's "Private Marketplaces": an invite-only group of Businesses
 * with their own confidential listings — the Coalition/HoldingSubsidiary
 * "parent aggregate + child membership table" shape applied to a new
 * concept. Marketplace itself has zero tables (a pure read-model), so this
 * cannot be an extension of it; it's a sibling new domain, same "new domain
 * when nothing fits" precedent as Growth/Llm/Holding/Approval before it.
 * Framework-free (Domain Layer Rules).
 */
final class PrivateMarketplace
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $ownerBusinessId,
        private string $nameFa,
        private string $nameEn,
        private ?string $brandingPrimaryColor,
        private PrivateMarketplaceStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $ownerBusinessId, string $nameFa, string $nameEn, ?string $brandingPrimaryColor = null): self
    {
        self::assertValidColor($brandingPrimaryColor);

        return new self(
            id: null,
            ownerBusinessId: $ownerBusinessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            brandingPrimaryColor: $brandingPrimaryColor,
            status: PrivateMarketplaceStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function updateBranding(?string $brandingPrimaryColor): void
    {
        self::assertValidColor($brandingPrimaryColor);
        $this->brandingPrimaryColor = $brandingPrimaryColor;
    }

    public function archive(): void
    {
        $this->status = PrivateMarketplaceStatus::Archived;
    }

    public function isActive(): bool
    {
        return $this->status === PrivateMarketplaceStatus::Active;
    }

    private static function assertValidColor(?string $color): void
    {
        if ($color !== null && ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException("brandingPrimaryColor must be a 6-digit hex color like #00F0FF, got [{$color}].");
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function ownerBusinessId(): int
    {
        return $this->ownerBusinessId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function brandingPrimaryColor(): ?string
    {
        return $this->brandingPrimaryColor;
    }

    public function status(): PrivateMarketplaceStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
