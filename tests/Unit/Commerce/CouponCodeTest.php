<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use PHPUnit\Framework\TestCase;

class CouponCodeTest extends TestCase
{
    public function test_construct_withValidFormat_normalizesToUppercase(): void
    {
        $code = new CouponCode('coupon-ab12c');

        $this->assertSame('COUPON-AB12C', $code->value());
    }

    public function test_construct_withInvalidFormat_throwsInvalidCouponException(): void
    {
        $this->expectException(InvalidCouponException::class);

        new CouponCode('DISCOUNT-12345');
    }

    public function test_construct_withWrongSuffixLength_throwsInvalidCouponException(): void
    {
        $this->expectException(InvalidCouponException::class);

        new CouponCode('COUPON-1234');
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new CouponCode('COUPON-AB12C');
        $b = new CouponCode('coupon-ab12c');

        $this->assertTrue($a->equals($b));
    }
}
