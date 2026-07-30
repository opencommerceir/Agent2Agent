<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Exceptions\CouponExpiredException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * Answers "can this already-loaded Coupon be used against this order
 * subtotal right now?" — pure business logic, no repository dependency
 * (Domain Layer Rules). $now defaults to the real current time but is
 * an explicit parameter so expiration checks are deterministic in
 * tests, without needing a Clock abstraction this phase doesn't
 * otherwise require.
 */
final class CouponValidationService
{
    public function validate(Coupon $coupon, Money $orderSubtotal, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable();

        if (! $coupon->isActive()) {
            throw new InvalidCouponException("Coupon [{$coupon->code()}] is not active.");
        }

        if ($coupon->isExpired($now)) {
            throw new CouponExpiredException("Coupon [{$coupon->code()}] has expired.");
        }

        if ($coupon->hasReachedMaxUses()) {
            throw new InvalidCouponException("Coupon [{$coupon->code()}] has reached its maximum number of uses.");
        }

        if (! $coupon->meetsMinimumOrderAmount($orderSubtotal)) {
            throw new InvalidCouponException(
                "Coupon [{$coupon->code()}] requires a minimum order amount of [{$coupon->minOrderAmount()}]."
            );
        }
    }
}
