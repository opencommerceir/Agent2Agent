<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Services\SubscriptionBillingCalculator;
use App\Modules\Commerce\Domain\Services\SubscriptionRenewalService;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionRenewalServiceTest extends TestCase
{
    private SubscriptionRenewalService $service;

    protected function setUp(): void
    {
        $this->service = new SubscriptionRenewalService(new SubscriptionBillingCalculator());
    }

    public function test_nextPeriod_onTime_startsFromCurrentPeriodEnd(): void
    {
        $periodEnd = new DateTimeImmutable('2026-02-01');
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), $periodEnd, null);

        $period = $this->service->nextPeriod($subscription, BillingCycle::Monthly, new DateTimeImmutable('2026-02-01'));

        $this->assertEquals($periodEnd, $period['start']);
        $this->assertSame('2026-03-01', $period['end']->format('Y-m-d'));
    }

    public function test_nextPeriod_whenRunLate_startsFromNowNotTheStalePeriodEnd(): void
    {
        $periodEnd = new DateTimeImmutable('2026-02-01');
        $subscription = Subscription::startActive(1, 10, 100, new DateTimeImmutable('2026-01-01'), $periodEnd, null);

        $lateNow = new DateTimeImmutable('2026-02-05');
        $period = $this->service->nextPeriod($subscription, BillingCycle::Monthly, $lateNow);

        $this->assertEquals($lateNow, $period['start']);
        $this->assertSame('2026-03-05', $period['end']->format('Y-m-d'));
    }
}
