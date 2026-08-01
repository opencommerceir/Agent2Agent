<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Core\Domain\ValueObjects\HashedPassword;
use App\Core\Domain\ValueObjects\UserRole;
use App\Core\Domain\ValueObjects\UserStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 */
class UserTest extends TestCase
{
    public function test_register_createsAnActiveUserWithTheGivenRole(): void
    {
        $user = User::register('Ada Lovelace', new Email('ada@example.com'), HashedPassword::fromPlainText('secret'), UserRole::Admin);

        $this->assertNull($user->id());
        $this->assertSame('Ada Lovelace', $user->name());
        $this->assertSame('ada@example.com', $user->email()->value());
        $this->assertSame(UserRole::Admin, $user->role());
        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isAdmin());
    }

    public function test_verifyPassword_withCorrectPassword_returnsTrue(): void
    {
        $user = User::register('Ada', new Email('ada@example.com'), HashedPassword::fromPlainText('secret'), UserRole::Operator);

        $this->assertTrue($user->verifyPassword('secret'));
        $this->assertFalse($user->verifyPassword('wrong'));
    }

    public function test_deactivate_thenActivate_togglesStatus(): void
    {
        $user = User::register('Ada', new Email('ada@example.com'), HashedPassword::fromPlainText('secret'), UserRole::Operator);

        $user->deactivate();
        $this->assertFalse($user->isActive());
        $this->assertSame(UserStatus::Inactive, $user->status());

        $user->activate();
        $this->assertTrue($user->isActive());
    }

    public function test_changeRole_updatesIsAdmin(): void
    {
        $user = User::register('Ada', new Email('ada@example.com'), HashedPassword::fromPlainText('secret'), UserRole::Operator);

        $this->assertFalse($user->isAdmin());

        $user->changeRole(UserRole::Admin);

        $this->assertTrue($user->isAdmin());
    }

    public function test_rename_andChangeEmail_updateTheirFields(): void
    {
        $user = User::register('Ada', new Email('ada@example.com'), HashedPassword::fromPlainText('secret'), UserRole::Operator);

        $user->rename('Ada Byron');
        $user->changeEmail(new Email('ada.byron@example.com'));

        $this->assertSame('Ada Byron', $user->name());
        $this->assertSame('ada.byron@example.com', $user->email()->value());
    }
}
