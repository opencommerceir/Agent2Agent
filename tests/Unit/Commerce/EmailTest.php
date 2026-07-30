<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidEmailException;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function test_construct_withValidAddress_normalizesToLowercase(): void
    {
        $email = new Email('Jane@Example.COM');

        $this->assertSame('jane@example.com', $email->value());
    }

    public function test_construct_withInvalidFormat_throwsInvalidEmailException(): void
    {
        $this->expectException(InvalidEmailException::class);

        new Email('not-an-email');
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new Email('jane@example.com');
        $b = new Email('Jane@Example.com');

        $this->assertTrue($a->equals($b));
    }

    public function test_toString_returnsNormalizedValue(): void
    {
        $email = new Email('Jane@Example.com');

        $this->assertSame('jane@example.com', (string) $email);
    }
}
