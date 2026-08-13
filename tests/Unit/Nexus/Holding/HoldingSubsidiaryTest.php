<?php

namespace Tests\Unit\Nexus\Holding;

use App\Domains\Nexus\Holding\Domain\Entities\HoldingSubsidiary;
use App\Domains\Nexus\Holding\Domain\Exceptions\InvalidSubsidiaryStateException;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;
use PHPUnit\Framework\TestCase;

class HoldingSubsidiaryTest extends TestCase
{
    public function test_invite_startsInInvited(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);

        $this->assertSame(SubsidiaryStatus::Invited, $subsidiary->status());
        $this->assertNull($subsidiary->respondedAt());
    }

    public function test_accept_fromInvited_transitionsToActiveAndSetsRespondedAt(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);

        $subsidiary->accept();

        $this->assertSame(SubsidiaryStatus::Active, $subsidiary->status());
        $this->assertNotNull($subsidiary->respondedAt());
    }

    public function test_remove_fromInvited_transitionsToRemoved(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);

        $subsidiary->remove();

        $this->assertSame(SubsidiaryStatus::Removed, $subsidiary->status());
    }

    public function test_remove_fromActive_transitionsToRemoved(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);
        $subsidiary->accept();

        $subsidiary->remove();

        $this->assertSame(SubsidiaryStatus::Removed, $subsidiary->status());
    }

    public function test_accept_fromRemoved_throws(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);
        $subsidiary->remove();

        $this->expectException(InvalidSubsidiaryStateException::class);

        $subsidiary->accept();
    }

    public function test_remove_fromRemoved_throws(): void
    {
        $subsidiary = HoldingSubsidiary::invite(1, 2);
        $subsidiary->remove();

        $this->expectException(InvalidSubsidiaryStateException::class);

        $subsidiary->remove();
    }
}
