<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Exceptions\CouponExpiredException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Services\CouponValidationService;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CouponValidationServiceTest extends TestCase
{
    public function test_validate_withValidCoupon_doesNotThrow(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10);

        (new CouponValidationService())->validate($coupon, Money::fromAmount(10000, 'USD'));

        $this->assertTrue(true);
    }

    public function test_validate_withExpiredCoupon_throwsCouponExpiredException(): void
    {
        $coupon = Coupon::create(
            1,
            new CouponCode('COUPON-AB12C'),
            DiscountType::Percentage,
            10,
            expiresAt: new DateTimeImmutable('2020-01-01'),
        );

        $this->expectException(CouponExpiredException::class);

        (new CouponValidationService())->validate($coupon, Money::fromAmount(10000, 'USD'), new DateTimeImmutable('2026-01-01'));
    }

    public function test_validate_withMaxUsesReached_throwsInvalidCouponException(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10, maxUses: 1);
        $coupon->recordUsage();

        $this->expectException(InvalidCouponException::class);

        (new CouponValidationService())->validate($coupon, Money::fromAmount(10000, 'USD'));
    }

    public function test_validate_belowMinimumOrderAmount_throwsInvalidCouponException(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10, minOrderAmount: 5000);

        $this->expectException(InvalidCouponException::class);

        (new CouponValidationService())->validate($coupon, Money::fromAmount(1000, 'USD'));
    }

    public function test_validate_whenDeactivated_throwsInvalidCouponException(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10);
        $coupon->deactivate();

        $this->expectException(InvalidCouponException::class);

        (new CouponValidationService())->validate($coupon, Money::fromAmount(10000, 'USD'));
    }
}
