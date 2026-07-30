<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    public function test_calculateDiscount_withPercentageType_computesWholePercentOfSubtotal(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10);

        $discount = $coupon->calculateDiscount(Money::fromAmount(10000, 'USD'));

        $this->assertSame(1000, $discount->amount());
    }

    public function test_calculateDiscount_withFixedAmountType_returnsTheFixedAmount(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::FixedAmount, 500);

        $discount = $coupon->calculateDiscount(Money::fromAmount(10000, 'USD'));

        $this->assertSame(500, $discount->amount());
    }

    public function test_calculateDiscount_withFixedAmountLargerThanSubtotal_clampsToSubtotal(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::FixedAmount, 500);

        $discount = $coupon->calculateDiscount(Money::fromAmount(300, 'USD'));

        $this->assertSame(300, $discount->amount());
    }

    public function test_recordUsage_incrementsUsedCount(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10);

        $coupon->recordUsage();
        $coupon->recordUsage();

        $this->assertSame(2, $coupon->usedCount());
    }

    public function test_hasReachedMaxUses_afterReachingLimit_returnsTrue(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10, maxUses: 2);

        $coupon->recordUsage();
        $this->assertFalse($coupon->hasReachedMaxUses());

        $coupon->recordUsage();
        $this->assertTrue($coupon->hasReachedMaxUses());
    }

    public function test_isExpired_afterExpiryDate_returnsTrue(): void
    {
        $coupon = Coupon::create(
            1,
            new CouponCode('COUPON-AB12C'),
            DiscountType::Percentage,
            10,
            expiresAt: new DateTimeImmutable('2026-01-01'),
        );

        $this->assertTrue($coupon->isExpired(new DateTimeImmutable('2026-06-01')));
        $this->assertFalse($coupon->isExpired(new DateTimeImmutable('2025-06-01')));
    }

    public function test_meetsMinimumOrderAmount_belowMinimum_returnsFalse(): void
    {
        $coupon = Coupon::create(1, new CouponCode('COUPON-AB12C'), DiscountType::Percentage, 10, minOrderAmount: 5000);

        $this->assertFalse($coupon->meetsMinimumOrderAmount(Money::fromAmount(1000, 'USD')));
        $this->assertTrue($coupon->meetsMinimumOrderAmount(Money::fromAmount(5000, 'USD')));
    }
}
