<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxRegionTest extends TestCase
{
    public function test_construct_withValidRegion_normalizesToUppercase(): void
    {
        $region = new TaxRegion('us-ca');

        $this->assertSame('US-CA', $region->value());
        $this->assertFalse($region->isDefault());
    }

    public function test_construct_withMalformedRegion_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxRegion('california');
    }

    public function test_default_returnsReservedDefaultRegion(): void
    {
        $region = TaxRegion::default();

        $this->assertSame('DEFAULT', $region->value());
        $this->assertTrue($region->isDefault());
    }

    public function test_construct_withLowercaseDefault_isAcceptedAsDefault(): void
    {
        $region = new TaxRegion('default');

        $this->assertTrue($region->isDefault());
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $a = new TaxRegion('US-CA');
        $b = new TaxRegion('us-ca');

        $this->assertTrue($a->equals($b));
    }
}
