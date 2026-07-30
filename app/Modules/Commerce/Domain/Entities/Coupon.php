<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A reusable, tenant-defined discount code — distinct from Discount,
 * which is the frozen record of one specific application of a Coupon (or
 * some other future discount source) to one Order. discountValue's
 * meaning depends on discountType: for Percentage it is a whole percent
 * (0-100, same range TaxRate validates); for FixedAmount it is the
 * smallest currency unit (cents), never a float — see
 * calculateDiscount().
 */
final class Coupon
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly CouponCode $code,
        private readonly DiscountType $discountType,
        private readonly int $discountValue,
        private readonly ?int $minOrderAmount,
        private readonly ?int $maxUses,
        private int $usedCount,
        private readonly ?DateTimeImmutable $expiresAt,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        CouponCode $code,
        DiscountType $discountType,
        int $discountValue,
        ?int $minOrderAmount = null,
        ?int $maxUses = null,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            code: $code,
            discountType: $discountType,
            discountValue: $discountValue,
            minOrderAmount: $minOrderAmount,
            maxUses: $maxUses,
            usedCount: 0,
            expiresAt: $expiresAt,
            isActive: true,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * How much discount this Coupon grants against a given subtotal —
     * the Coupon's own business rule, not PricingService's (PricingService
     * only knows how to combine an already-computed discount into a
     * total). All-integer arithmetic: Percentage never introduces a
     * float, and FixedAmount is clamped so a discount can never exceed
     * the subtotal (Total = Subtotal + Tax - Discount could otherwise go
     * negative).
     */
    public function calculateDiscount(Money $subtotal): Money
    {
        $amount = match ($this->discountType) {
            DiscountType::Percentage => intdiv($subtotal->amount() * $this->discountValue, 100),
            DiscountType::FixedAmount => min($this->discountValue, $subtotal->amount()),
        };

        return Money::fromAmount($amount, $subtotal->currency());
    }

    public function recordUsage(): void
    {
        $this->usedCount++;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $now > $this->expiresAt;
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->maxUses !== null && $this->usedCount >= $this->maxUses;
    }

    public function meetsMinimumOrderAmount(Money $subtotal): bool
    {
        return $this->minOrderAmount === null || $subtotal->amount() >= $this->minOrderAmount;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function code(): CouponCode
    {
        return $this->code;
    }

    public function discountType(): DiscountType
    {
        return $this->discountType;
    }

    public function discountValue(): int
    {
        return $this->discountValue;
    }

    public function minOrderAmount(): ?int
    {
        return $this->minOrderAmount;
    }

    public function maxUses(): ?int
    {
        return $this->maxUses;
    }

    public function usedCount(): int
    {
        return $this->usedCount;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
