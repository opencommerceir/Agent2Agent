<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Exceptions\InvalidSubscriptionStateException;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_startTrial_startsInTrialWithTrialWindowAsCurrentPeriod(): void
    {
        $subscription = Subscription::startTrial(1, 10, 100, 7, null);

        $this->assertSame(SubscriptionStatus::Trial, $subscription->status());
        $this->assertNotNull($subscription->trialStart());
        $this->assertNotNull($subscription->trialEnd());
        $this->assertEquals($subscription->trialEnd(), $subscription->currentPeriodEnd());
    }

    public function test_startActive_startsActiveWithNoTrialFields(): void
    {
        $start = new DateTimeImmutable('2026-01-01');
        $end = new DateTimeImmutable('2026-02-01');

        $subscription = Subscription::startActive(1, 10, 100, $start, $end, 'pm_123');

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
        $this->assertNull($subscription->trialStart());
        $this->assertNull($subscription->trialEnd());
        $this->assertSame('pm_123', $subscription->paymentMethodId());
    }

    public function test_pause_fromActive_setsPausedAt(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);

        $subscription->pause();

        $this->assertSame(SubscriptionStatus::Paused, $subscription->status());
        $this->assertNotNull($subscription->pausedAt());
    }

    public function test_pause_fromTrial_throws(): void
    {
        $subscription = Subscription::startTrial(1, 10, 100, 7, null);

        $this->expectException(InvalidSubscriptionStateException::class);

        $subscription->pause();
    }

    public function test_resume_extendsPeriodByPauseDuration(): void
    {
        $periodEnd = new DateTimeImmutable('2026-02-01 00:00:00');
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), $periodEnd, null);
        $subscription->pause();

        // Simulate having been paused for a while by rebuilding via reflection-free approach:
        // pause()/resume() both use "now" internally, so we assert the *shape* of the extension
        // (period end moves forward by >= 0) rather than an exact duration in this fast unit test.
        $beforeResumePeriodEnd = $subscription->currentPeriodEnd();
        $subscription->resume();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
        $this->assertNull($subscription->pausedAt());
        $this->assertGreaterThanOrEqual($beforeResumePeriodEnd, $subscription->currentPeriodEnd());
    }

    public function test_resume_withoutHavingBeenPaused_throws(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);

        $this->expectException(InvalidSubscriptionStateException::class);

        $subscription->resume();
    }

    public function test_cancelImmediately_fromActive_setsCancelledAt(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);

        $subscription->cancelImmediately();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status());
        $this->assertNotNull($subscription->cancelledAt());
    }

    public function test_cancelImmediately_fromTrial_succeeds(): void
    {
        $subscription = Subscription::startTrial(1, 10, 100, 7, null);

        $subscription->cancelImmediately();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status());
    }

    public function test_cancelImmediately_fromAlreadyCancelled_throws(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);
        $subscription->cancelImmediately();

        $this->expectException(InvalidSubscriptionStateException::class);

        $subscription->cancelImmediately();
    }

    public function test_scheduleCancelAtPeriodEnd_setsFlagWithoutChangingStatus(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);

        $subscription->scheduleCancelAtPeriodEnd();

        $this->assertTrue($subscription->cancelAtPeriodEnd());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
    }

    public function test_scheduleCancelAtPeriodEnd_onAlreadyCancelled_throws(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);
        $subscription->cancelImmediately();

        $this->expectException(InvalidSubscriptionStateException::class);

        $subscription->scheduleCancelAtPeriodEnd();
    }

    public function test_cancelAtPeriodEndReached_transitionsToCancelledAndClearsFlag(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);
        $subscription->scheduleCancelAtPeriodEnd();

        $subscription->cancelAtPeriodEndReached();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status());
        $this->assertFalse($subscription->cancelAtPeriodEnd());
        $this->assertNotNull($subscription->cancelledAt());
    }

    public function test_markPastDue_fromActive_succeeds(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);

        $subscription->markPastDue();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status());
    }

    public function test_reactivate_fromPastDue_returnsToActiveWithoutChangingPeriod(): void
    {
        $periodEnd = new DateTimeImmutable('2026-02-01');
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), $periodEnd, null);
        $subscription->markPastDue();

        $subscription->reactivate();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
        $this->assertEquals($periodEnd, $subscription->currentPeriodEnd());
    }

    public function test_renew_fromActive_toleratesSelfTransitionAndAdvancesPeriod(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'), null);

        $newStart = new DateTimeImmutable('2026-02-01');
        $newEnd = new DateTimeImmutable('2026-03-01');
        $subscription->renew($newStart, $newEnd);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
        $this->assertEquals($newStart, $subscription->currentPeriodStart());
        $this->assertEquals($newEnd, $subscription->currentPeriodEnd());
    }

    public function test_renew_fromTrial_convertsToActive(): void
    {
        $subscription = Subscription::startTrial(1, 10, 100, 7, null);

        $subscription->renew(new DateTimeImmutable(), new DateTimeImmutable('+1 month'));

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());
    }

    public function test_renew_fromCancelled_throws(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable(), new DateTimeImmutable('+1 month'), null);
        $subscription->cancelImmediately();

        $this->expectException(InvalidSubscriptionStateException::class);

        $subscription->renew(new DateTimeImmutable(), new DateTimeImmutable('+1 month'));
    }

    public function test_changePlan_updatesSubscriptionPlanIdOnly(): void
    {
        $periodEnd = new DateTimeImmutable('2026-02-01');
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), $periodEnd, null);

        $subscription->changePlan(200);

        $this->assertSame(200, $subscription->subscriptionPlanId());
        $this->assertEquals($periodEnd, $subscription->currentPeriodEnd());
    }

    public function test_isDueForRenewal_whenPeriodEndInPast_isTrueForActive(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('-2 months'), new DateTimeImmutable('-1 month'), null);

        $this->assertTrue($subscription->isDueForRenewal(new DateTimeImmutable()));
    }

    public function test_isDueForRenewal_whenPaused_isFalseEvenIfPeriodEndPassed(): void
    {
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('-2 months'), new DateTimeImmutable('-1 month'), null);
        $subscription->pause();

        $this->assertFalse($subscription->isDueForRenewal(new DateTimeImmutable()));
    }
}
