<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\VariantAttribute;
use PHPUnit\Framework\TestCase;

class VariantAttributeTest extends TestCase
{
    public function test_create_buildsOneValueEntityPerGivenValue(): void
    {
        $attribute = VariantAttribute::create(1, 'Color', ['Red', 'Blue', 'Black']);

        $this->assertSame(1, $attribute->tenantId());
        $this->assertSame('Color', $attribute->name());
        $this->assertCount(3, $attribute->values());
        $this->assertSame('Red', $attribute->values()[0]->value());
        $this->assertSame('Blue', $attribute->values()[1]->value());
        $this->assertSame('Black', $attribute->values()[2]->value());
    }

    public function test_create_assignsDisplayOrderByPositionToEachValue(): void
    {
        $attribute = VariantAttribute::create(1, 'Size', ['S', 'M', 'L']);

        $this->assertSame(0, $attribute->values()[0]->displayOrder());
        $this->assertSame(1, $attribute->values()[1]->displayOrder());
        $this->assertSame(2, $attribute->values()[2]->displayOrder());
    }

    public function test_create_defaultsOwnDisplayOrderToZero(): void
    {
        $attribute = VariantAttribute::create(1, 'Color', ['Red']);

        $this->assertSame(0, $attribute->displayOrder());
    }
}
