<?php

namespace Tests\Unit\Nexus\Contract;

use App\Domains\Nexus\Contract\Domain\Entities\DisputeCase;
use App\Domains\Nexus\Contract\Domain\Exceptions\InvalidDisputeCaseStateException;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 * DisputeCase is framework-free by design (Domain Layer Rules).
 */
class DisputeCaseTest extends TestCase
{
    private function openCase(): DisputeCase
    {
        return DisputeCase::open(
            escrowId: 1,
            negotiationId: 2,
            businessAId: 10,
            businessBId: 20,
            openedByBusinessId: 10,
            reason: 'never delivered',
        );
    }

    public function test_open_startsInOpenStatusWithNoEvidence(): void
    {
        $case = $this->openCase();

        $this->assertSame(DisputeCaseStatus::Open, $case->status());
        $this->assertSame([], $case->evidence());
    }

    public function test_addEvidence_byParty_appendsEntry(): void
    {
        $case = $this->openCase();

        $case->addEvidence(20, 'here is proof of delivery');

        $this->assertCount(1, $case->evidence());
        $this->assertSame(20, $case->evidence()[0]['businessId']);
        $this->assertSame('here is proof of delivery', $case->evidence()[0]['note']);
    }

    public function test_addEvidence_byNonParty_throws(): void
    {
        $case = $this->openCase();

        $this->expectException(InvalidArgumentException::class);

        $case->addEvidence(999, 'not my business');
    }

    public function test_moveToMediation_thenResolve_reachesResolved(): void
    {
        $case = $this->openCase();

        $case->moveToMediation();
        $this->assertSame(DisputeCaseStatus::Mediation, $case->status());

        $case->resolve('refund_buyer');
        $this->assertSame(DisputeCaseStatus::Resolved, $case->status());
        $this->assertSame('refund_buyer', $case->resolution());
        $this->assertNotNull($case->resolvedAt());
    }

    public function test_resolve_directlyFromOpen_isAllowed(): void
    {
        $case = $this->openCase();

        $case->resolve('release_seller');

        $this->assertSame(DisputeCaseStatus::Resolved, $case->status());
    }

    public function test_resolve_withUnknownResolution_throws(): void
    {
        $case = $this->openCase();

        $this->expectException(InvalidArgumentException::class);

        $case->resolve('something_else');
    }

    public function test_moveToMediation_onResolvedCase_throws(): void
    {
        $case = $this->openCase();
        $case->resolve('refund_buyer');

        $this->expectException(InvalidDisputeCaseStateException::class);

        $case->moveToMediation();
    }

    public function test_isParty_reflectsBothBusinessIds(): void
    {
        $case = $this->openCase();

        $this->assertTrue($case->isParty(10));
        $this->assertTrue($case->isParty(20));
        $this->assertFalse($case->isParty(30));
    }
}
