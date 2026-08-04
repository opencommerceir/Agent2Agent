<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CouponData;
use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * discountValue's meaning depends on discountType — see Coupon's own
 * docblock: a whole percent (0-100) for Percentage, or the smallest
 * currency unit (cents) for FixedAmount.
 */
final class CreateCouponAction
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
    ) {
    }

    public function execute(
        int $tenantId,
        string $code,
        string $discountType,
        int $discountValue,
        ?int $minOrderAmount = null,
        ?int $maxUses = null,
        ?string $expiresAt = null,
        ?int $discountRuleId = null,
    ): CouponData {
        $codeValue = new CouponCode($code); // throws InvalidCouponException on bad format

        if ($this->coupons->codeExists($codeValue, $tenantId)) {
            throw new InvalidArgumentException("Coupon code [{$codeValue}] is already taken in this tenant.");
        }

        $coupon = Coupon::create(
            tenantId: $tenantId,
            code: $codeValue,
            discountType: DiscountType::from($discountType),
            discountValue: $discountValue,
            minOrderAmount: $minOrderAmount,
            maxUses: $maxUses,
            expiresAt: $expiresAt !== null ? new DateTimeImmutable($expiresAt) : null,
            discountRuleId: $discountRuleId,
        );

        $coupon = $this->coupons->save($coupon);

        return CouponData::fromEntity($coupon);
    }
}
