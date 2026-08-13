<?php

namespace Tests\Unit\Nexus\PrivateMarketplace;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceMember;
use App\Domains\Nexus\PrivateMarketplace\Domain\Exceptions\InvalidPrivateMarketplaceMemberStateException;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceMemberStatus;
use PHPUnit\Framework\TestCase;

class PrivateMarketplaceMemberTest extends TestCase
{
    public function test_invite_startsInInvited(): void
    {
        $member = PrivateMarketplaceMember::invite(1, 2);

        $this->assertSame(PrivateMarketplaceMemberStatus::Invited, $member->status());
    }

    public function test_accept_transitionsToActive(): void
    {
        $member = PrivateMarketplaceMember::invite(1, 2);

        $member->accept();

        $this->assertSame(PrivateMarketplaceMemberStatus::Active, $member->status());
    }

    public function test_remove_fromActive_transitionsToRemoved(): void
    {
        $member = PrivateMarketplaceMember::invite(1, 2);
        $member->accept();

        $member->remove();

        $this->assertSame(PrivateMarketplaceMemberStatus::Removed, $member->status());
    }

    public function test_accept_fromRemoved_throws(): void
    {
        $member = PrivateMarketplaceMember::invite(1, 2);
        $member->remove();

        $this->expectException(InvalidPrivateMarketplaceMemberStateException::class);

        $member->accept();
    }
}
