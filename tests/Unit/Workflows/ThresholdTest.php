<?php

namespace Tests\Unit\Workflows;

use App\Modules\Workflows\Domain\ValueObjects\Threshold;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ThresholdTest extends TestCase
{
    public function test_construct_withPositiveValue_succeeds(): void
    {
        $threshold = new Threshold(5);

        $this->assertSame(5, $threshold->value());
    }

    public function test_construct_withZero_succeeds(): void
    {
        $threshold = new Threshold(0);

        $this->assertSame(0, $threshold->value());
    }

    public function test_construct_withNegativeValue_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Threshold(-1);
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $this->assertTrue((new Threshold(5))->equals(new Threshold(5)));
    }
}
