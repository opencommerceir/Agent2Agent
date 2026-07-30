<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * The frozen record of one discount applied to one Order — distinct
 * from Coupon, which is the reusable, still-editable definition a
 * Discount may (optionally) have come from. couponId is nullable so a
 * future non-coupon discount source (a loyalty program, a manual
 * price override) can still produce a Discount row. No mutators and no
 * id field, same immutability/shape OrderItem already established — a
 * historical fact nothing ever looks up by its own id, only by orderId
 * (DiscountRepositoryInterface::listByOrder()).
 */
final class Discount
{
    private function __construct(
        private readonly int $orderId,
        private readonly ?int $couponId,
        private readonly DiscountType $type,
        private readonly Money $amount,
        private readonly ?string $description,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function apply(
        int $orderId,
        ?int $couponId,
        DiscountType $type,
        Money $amount,
        ?string $description = null,
    ): self {
        return new self(
            orderId: $orderId,
            couponId: $couponId,
            type: $type,
            amount: $amount,
            description: $description,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function couponId(): ?int
    {
        return $this->couponId;
    }

    public function type(): DiscountType
    {
        return $this->type;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
