<?php

namespace App\Domains\Nexus\Catalog\Domain\Entities;

use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A physical/tangible item a Business sells. Framework-free (Domain Layer
 * Rules). Variants (docs/nexus-roadmap.md mentions them) are deliberately
 * out of scope for Phase 1 — none of the 4 named Catalog Actions
 * (AddProduct/AddService/UpdateCatalog/SearchCatalog) require them yet;
 * $attributes (a plain JSON bag) is where industry-specific custom fields
 * live instead of one migration per industry.
 *
 * `verificationStatus` (Phase 6/M5) starts Pending on every add() and is
 * never touched by update() — an admin re-reviews a changed listing
 * separately (VerifyProductAction/RejectProductAction), the same "editing
 * doesn't silently re-earn trust" reasoning Business's own verify() never
 * needing to re-run on updateProfile() already establishes.
 */
final class Product
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private string $nameFa,
        private string $nameEn,
        private Money $price,
        private int $stockQuantity,
        private ?array $attributes,
        private readonly DateTimeImmutable $createdAt,
        private ListingVerificationStatus $verificationStatus = ListingVerificationStatus::Pending,
    ) {
    }

    public static function add(
        int $businessId,
        string $nameFa,
        string $nameEn,
        Money $price,
        int $stockQuantity = 0,
        ?array $attributes = null,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            price: $price,
            stockQuantity: $stockQuantity,
            attributes: $attributes,
            createdAt: new DateTimeImmutable(),
            verificationStatus: ListingVerificationStatus::Pending,
        );
    }

    public function update(string $nameFa, string $nameEn, Money $price, int $stockQuantity, ?array $attributes): void
    {
        $this->nameFa = $nameFa;
        $this->nameEn = $nameEn;
        $this->price = $price;
        $this->stockQuantity = $stockQuantity;
        $this->attributes = $attributes;
    }

    public function verify(): void
    {
        $this->verificationStatus = ListingVerificationStatus::Verified;
    }

    public function reject(): void
    {
        $this->verificationStatus = ListingVerificationStatus::Rejected;
    }

    public function isVerified(): bool
    {
        return $this->verificationStatus === ListingVerificationStatus::Verified;
    }

    public function verificationStatus(): ListingVerificationStatus
    {
        return $this->verificationStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function stockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function attributes(): ?array
    {
        return $this->attributes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
