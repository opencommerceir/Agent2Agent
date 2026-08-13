<?php

namespace Tests\Unit\Nexus\Automation;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Exceptions\InvalidAutomationRuleStateException;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleStatus;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AutomationRuleTest extends TestCase
{
    public function test_forRecurringOrder_startsActive(): void
    {
        $rule = AutomationRule::forRecurringOrder(1, 2, CatalogItemType::Product, 5, 10_000, 'IRT', 2, 30);

        $this->assertSame(AutomationRuleStatus::Active, $rule->status());
        $this->assertNull($rule->lastTriggeredAt());
        $this->assertSame(30, $rule->config()['intervalDays']);
    }

    public function test_forRecurringOrder_sameBusinessAsCounterparty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AutomationRule::forRecurringOrder(1, 1, CatalogItemType::Product, 5, 10_000, 'IRT', 1, 30);
    }

    public function test_forRecurringOrder_intervalBelowOne_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AutomationRule::forRecurringOrder(1, 2, CatalogItemType::Product, 5, 10_000, 'IRT', 1, 0);
    }

    public function test_forInventoryAlert_negativeThreshold_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AutomationRule::forInventoryAlert(1, 10, -1);
    }

    public function test_forPriceAlert_storesDirectionInConfig(): void
    {
        $rule = AutomationRule::forPriceAlert(1, CatalogItemType::Service, 3, 5_000, 'IRT', PriceAlertDirection::AtOrAbove);

        $this->assertSame('at_or_above', $rule->config()['direction']);
    }

    public function test_pause_thenResume_roundTrips(): void
    {
        $rule = AutomationRule::forInventoryAlert(1, 10, 5);

        $rule->pause();
        $this->assertSame(AutomationRuleStatus::Paused, $rule->status());

        $rule->resume();
        $this->assertSame(AutomationRuleStatus::Active, $rule->status());
    }

    public function test_pause_whenAlreadyPaused_throws(): void
    {
        $rule = AutomationRule::forInventoryAlert(1, 10, 5);
        $rule->pause();

        $this->expectException(InvalidAutomationRuleStateException::class);

        $rule->pause();
    }

    public function test_canRetriggerAt_trueWhenNeverTriggered(): void
    {
        $rule = AutomationRule::forInventoryAlert(1, 10, 5);

        $this->assertTrue($rule->canRetriggerAt(new DateTimeImmutable(), 24));
    }

    public function test_canRetriggerAt_falseWithinCooldownWindow(): void
    {
        $rule = AutomationRule::forInventoryAlert(1, 10, 5);
        $now = new DateTimeImmutable('2026-01-01 00:00:00');
        $rule->recordTrigger($now);

        $this->assertFalse($rule->canRetriggerAt($now->modify('+1 hour'), 24));
    }

    public function test_canRetriggerAt_trueOnceCooldownElapsed(): void
    {
        $rule = AutomationRule::forInventoryAlert(1, 10, 5);
        $now = new DateTimeImmutable('2026-01-01 00:00:00');
        $rule->recordTrigger($now);

        $this->assertTrue($rule->canRetriggerAt($now->modify('+25 hours'), 24));
    }
}
