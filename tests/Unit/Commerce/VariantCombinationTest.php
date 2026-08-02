<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\VariantCombination;
use PHPUnit\Framework\TestCase;

class VariantCombinationTest extends TestCase
{
    public function test_attributeValues_returnsTheGivenMap(): void
    {
        $combination = new VariantCombination(['Color' => 'Red', 'Size' => 'L']);

        $this->assertSame(['Color' => 'Red', 'Size' => 'L'], $combination->attributeValues());
    }

    public function test_skuSuffix_returnsOrderedValuesOnly(): void
    {
        $combination = new VariantCombination(['Color' => 'Red', 'Size' => 'L']);

        $this->assertSame(['Red', 'L'], $combination->skuSuffix());
    }
}
