<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Exceptions\InvalidEmailException;
use App\Core\Domain\ValueObjects\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function test_construct_normalizesToLowercase(): void
    {
        $email = new Email('Jane@Example.COM');

        $this->assertSame('jane@example.com', $email->value());
    }

    public function test_construct_withInvalidFormat_throws(): void
    {
        $this->expectException(InvalidEmailException::class);

        new Email('not-an-email');
    }

    public function test_equals_comparesNormalizedValue(): void
    {
        $a = new Email('Jane@Example.com');
        $b = new Email('jane@example.com');

        $this->assertTrue($a->equals($b));
    }
}
