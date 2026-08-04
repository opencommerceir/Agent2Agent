<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DiscountPriorityTest extends TestCase
{
    public function test_construct_withNegativeValue_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DiscountPriority(-1);
    }

    public function test_isHigherThan_comparesValues(): void
    {
        $high = new DiscountPriority(10);
        $low = new DiscountPriority(5);

        $this->assertTrue($high->isHigherThan($low));
        $this->assertFalse($low->isHigherThan($high));
    }
}
