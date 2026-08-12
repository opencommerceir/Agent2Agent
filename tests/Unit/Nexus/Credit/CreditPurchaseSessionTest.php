<?php

namespace Tests\Unit\Nexus\Credit;

use App\Domains\Nexus\Credit\Domain\Entities\CreditPurchaseSession;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPurchaseSessionStatus;
use App\Domains\Nexus\Credit\Domain\ValueObjects\Money;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

class CreditPurchaseSessionTest extends TestCase
{
    private function newSession(): CreditPurchaseSession
    {
        return CreditPurchaseSession::create(1, 'zibal', CreditPackage::Starter, Money::fromAmount(500_000, 'IRT'));
    }

    public function test_create_startsPendingWithNoIdOrProviderReference(): void
    {
        $session = $this->newSession();

        $this->assertNull($session->id());
        $this->assertNull($session->providerReference());
        $this->assertSame(CreditPurchaseSessionStatus::Pending, $session->status());
        $this->assertTrue($session->isPending());
        $this->assertFalse($session->isCompleted());
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $session = $this->newSession();
        $session->assignId(1);

        $this->expectException(LogicException::class);

        $session->assignId(2);
    }

    public function test_markInitiated_isOneTimeOnly(): void
    {
        $session = $this->newSession();
        $session->markInitiated('trk_1');

        $this->expectException(LogicException::class);

        $session->markInitiated('trk_2');
    }

    public function test_complete_transitionsFromPending(): void
    {
        $session = $this->newSession();

        $session->complete();

        $this->assertSame(CreditPurchaseSessionStatus::Completed, $session->status());
        $this->assertTrue($session->isCompleted());
        $this->assertNotNull($session->completedAt());
    }

    public function test_fail_transitionsFromPending(): void
    {
        $session = $this->newSession();

        $session->fail();

        $this->assertSame(CreditPurchaseSessionStatus::Failed, $session->status());
    }

    public function test_complete_afterAlreadyCompleted_throws(): void
    {
        $session = $this->newSession();
        $session->complete();

        $this->expectException(InvalidArgumentException::class);

        $session->complete();
    }
}
