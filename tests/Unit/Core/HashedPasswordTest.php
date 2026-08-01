<?php

namespace Tests\Unit\Core;

use App\Core\Domain\ValueObjects\HashedPassword;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free — uses PHP's own password_hash()/password_verify(),
 * never Laravel's Hash facade (HashedPassword's own docblock).
 */
class HashedPasswordTest extends TestCase
{
    public function test_fromPlainText_thenVerify_withCorrectPassword_returnsTrue(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertTrue($password->verify('correct-horse-battery-staple'));
    }

    public function test_fromPlainText_thenVerify_withWrongPassword_returnsFalse(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertFalse($password->verify('wrong-password'));
    }

    public function test_fromPlainText_neverStoresThePlainTextItself(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertNotSame('correct-horse-battery-staple', $password->value());
        $this->assertStringStartsWith('$2y$', $password->value());
    }

    public function test_fromHash_wrapsAnAlreadyHashedValueWithoutRehashing(): void
    {
        $original = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $reloaded = HashedPassword::fromHash($original->value());

        $this->assertSame($original->value(), $reloaded->value());
        $this->assertTrue($reloaded->verify('correct-horse-battery-staple'));
    }
}
