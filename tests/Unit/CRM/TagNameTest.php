<?php

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\ValueObjects\TagName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TagNameTest extends TestCase
{
    public function test_construct_withValidName_preservesCasing(): void
    {
        $name = new TagName('VIP Customer');

        $this->assertSame('VIP Customer', $name->value());
    }

    public function test_construct_collapsesInternalWhitespaceAndTrims(): void
    {
        $name = new TagName('  VIP    Customer  ');

        $this->assertSame('VIP Customer', $name->value());
    }

    public function test_construct_withEmptyString_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TagName('   ');
    }

    public function test_construct_exceedingMaxLength_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TagName(str_repeat('a', 51));
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new TagName('VIP Customer');
        $b = new TagName('  VIP   Customer ');

        $this->assertTrue($a->equals($b));
    }
}
