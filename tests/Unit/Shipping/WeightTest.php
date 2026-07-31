<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Domain\Exceptions\InvalidWeightException;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use PHPUnit\Framework\TestCase;

class WeightTest extends TestCase
{
    public function test_construct_withPositiveGrams_succeeds(): void
    {
        $weight = new Weight(2500);

        $this->assertSame(2500, $weight->grams());
    }

    public function test_construct_withZero_succeeds(): void
    {
        $weight = new Weight(0);

        $this->assertSame(0, $weight->grams());
    }

    public function test_construct_withNegativeGrams_throwsInvalidWeightException(): void
    {
        $this->expectException(InvalidWeightException::class);

        new Weight(-1);
    }

    public function test_kilograms_convertsFromGrams(): void
    {
        $weight = new Weight(2500);

        $this->assertSame(2.5, $weight->kilograms());
    }

    public function test_add_sumsGrams(): void
    {
        $sum = (new Weight(1000))->add(new Weight(1500));

        $this->assertSame(2500, $sum->grams());
    }

    public function test_equals_withSameGrams_returnsTrue(): void
    {
        $this->assertTrue((new Weight(500))->equals(new Weight(500)));
    }
}
