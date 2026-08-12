<?php

namespace Tests\Unit\Nexus\Business;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionAppeal;
use App\Domains\Nexus\Business\Domain\Exceptions\InvalidSuspensionAppealStateException;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 * SuspensionAppeal is framework-free by design (Domain Layer Rules).
 */
class SuspensionAppealTest extends TestCase
{
    public function test_submit_startsInPendingStatus(): void
    {
        $appeal = SuspensionAppeal::submit(1, 'I was suspended by mistake');

        $this->assertSame(SuspensionAppealStatus::Pending, $appeal->status());
        $this->assertNull($appeal->resolvedAt());
    }

    public function test_approve_transitionsToApprovedAndSetsResolvedAt(): void
    {
        $appeal = SuspensionAppeal::submit(1, 'reason');

        $appeal->approve();

        $this->assertSame(SuspensionAppealStatus::Approved, $appeal->status());
        $this->assertNotNull($appeal->resolvedAt());
    }

    public function test_deny_transitionsToDenied(): void
    {
        $appeal = SuspensionAppeal::submit(1, 'reason');

        $appeal->deny();

        $this->assertSame(SuspensionAppealStatus::Denied, $appeal->status());
    }

    public function test_approve_onAlreadyResolvedAppeal_throws(): void
    {
        $appeal = SuspensionAppeal::submit(1, 'reason');
        $appeal->deny();

        $this->expectException(InvalidSuspensionAppealStateException::class);

        $appeal->approve();
    }
}
