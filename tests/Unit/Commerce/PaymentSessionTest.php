<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\PaymentSession;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentSessionStatus;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

class PaymentSessionTest extends TestCase
{
    private function makeSession(): PaymentSession
    {
        return PaymentSession::create(
            tenantId: 1,
            cartId: 10,
            agentId: 5,
            gateway: 'zibal',
            total: Money::fromAmount(200000, 'IRR'),
            tax: Money::fromAmount(0, 'IRR'),
            discount: Money::fromAmount(0, 'IRR'),
            couponCode: null,
            customerId: null,
            notes: null,
            region: null,
        );
    }

    public function test_create_startsPending(): void
    {
        $session = $this->makeSession();

        $this->assertSame(PaymentSessionStatus::Pending, $session->status());
        $this->assertNull($session->id());
        $this->assertNull($session->providerReference());
        $this->assertNull($session->orderId());
        $this->assertTrue($session->isPending());
        $this->assertFalse($session->isCompleted());
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $session = $this->makeSession();
        $session->assignId(1);

        $this->assertSame(1, $session->id());

        $this->expectException(LogicException::class);

        $session->assignId(2);
    }

    public function test_markInitiated_isOneTimeOnly(): void
    {
        $session = $this->makeSession();
        $session->markInitiated('trk_123');

        $this->assertSame('trk_123', $session->providerReference());

        $this->expectException(LogicException::class);

        $session->markInitiated('trk_456');
    }

    public function test_complete_fromPending_setsOrderIdAndCompletedAt(): void
    {
        $session = $this->makeSession();

        $session->complete(42);

        $this->assertSame(PaymentSessionStatus::Completed, $session->status());
        $this->assertSame(42, $session->orderId());
        $this->assertNotNull($session->completedAt());
        $this->assertTrue($session->isCompleted());
    }

    public function test_fail_fromPending_succeeds(): void
    {
        $session = $this->makeSession();

        $session->fail();

        $this->assertSame(PaymentSessionStatus::Failed, $session->status());
        $this->assertNotNull($session->completedAt());
    }

    public function test_cancel_fromPending_succeeds(): void
    {
        $session = $this->makeSession();

        $session->cancel();

        $this->assertSame(PaymentSessionStatus::Cancelled, $session->status());
    }

    public function test_complete_fromAlreadyCompleted_throws(): void
    {
        $session = $this->makeSession();
        $session->complete(42);

        $this->expectException(InvalidArgumentException::class);

        $session->complete(43);
    }

    public function test_fail_fromAlreadyFailed_throws(): void
    {
        $session = $this->makeSession();
        $session->fail();

        $this->expectException(InvalidArgumentException::class);

        $session->fail();
    }

    public function test_pricingIsFrozenAtCreation(): void
    {
        $session = PaymentSession::create(
            tenantId: 1,
            cartId: 10,
            agentId: 5,
            gateway: 'stripe',
            total: Money::fromAmount(11000, 'USD'),
            tax: Money::fromAmount(1000, 'USD'),
            discount: Money::fromAmount(500, 'USD'),
        );

        $this->assertSame(11000, $session->total()->amount());
        $this->assertSame(1000, $session->tax()->amount());
        $this->assertSame(500, $session->discount()->amount());
        $this->assertSame('USD', $session->total()->currency());
    }
}
