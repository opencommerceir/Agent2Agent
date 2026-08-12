<?php

namespace Tests\Unit\Nexus\Growth;

use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoalitionMemberTest extends TestCase
{
    public function test_join_createsMemberWithQuantity(): void
    {
        $member = CoalitionMember::join(1, 2, 5);

        $this->assertSame(1, $member->coalitionId());
        $this->assertSame(2, $member->businessId());
        $this->assertSame(5, $member->quantity());
    }

    public function test_join_withZeroQuantity_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CoalitionMember::join(1, 2, 0);
    }
}
