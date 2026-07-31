<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Domain\Exceptions\InvalidPointsException;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use PHPUnit\Framework\TestCase;

class PointsTest extends TestCase
{
    public function test_construct_withPositiveValue_succeeds(): void
    {
        $points = new Points(150);

        $this->assertSame(150, $points->value());
    }

    public function test_construct_withZero_succeeds(): void
    {
        $points = new Points(0);

        $this->assertSame(0, $points->value());
    }

    public function test_construct_withNegativeValue_throwsInvalidPointsException(): void
    {
        $this->expectException(InvalidPointsException::class);

        new Points(-1);
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $this->assertTrue((new Points(100))->equals(new Points(100)));
    }

    public function test_equals_withDifferentValue_returnsFalse(): void
    {
        $this->assertFalse((new Points(100))->equals(new Points(50)));
    }
}
