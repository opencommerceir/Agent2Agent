<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\SubscriptionPlan;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class SubscriptionPlanTest extends TestCase
{
    public function test_create_withTrialDays_hasTrial(): void
    {
        $plan = SubscriptionPlan::create(
            tenantId: 1,
            name: 'Pro Monthly',
            description: null,
            billingCycle: BillingCycle::Monthly,
            price: Money::fromAmount(10000000, 'IRR'),
            trialDays: 7,
        );

        $this->assertTrue($plan->hasTrial());
        $this->assertSame(7, $plan->trialPeriod()->days());
    }

    public function test_create_withNoTrialDays_hasNoTrial(): void
    {
        $plan = SubscriptionPlan::create(
            tenantId: 1,
            name: 'Basic Monthly',
            description: null,
            billingCycle: BillingCycle::Monthly,
            price: Money::fromAmount(5000000, 'IRR'),
        );

        $this->assertFalse($plan->hasTrial());
    }

    public function test_create_withFeatures_preservesThem(): void
    {
        $plan = SubscriptionPlan::create(
            tenantId: 1,
            name: 'Pro Yearly',
            description: 'Full access',
            billingCycle: BillingCycle::Yearly,
            price: Money::fromAmount(100000000, 'IRR'),
            features: ['priority_support', 'api_access'],
        );

        $this->assertSame(['priority_support', 'api_access'], $plan->features());
        $this->assertSame(BillingCycle::Yearly, $plan->billingCycle());
    }

    public function test_create_defaultsToActive(): void
    {
        $plan = SubscriptionPlan::create(1, 'X', null, BillingCycle::Monthly, Money::fromAmount(1000, 'USD'));

        $this->assertTrue($plan->isActive());
    }
}
