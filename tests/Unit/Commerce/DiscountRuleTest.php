<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DiscountRuleTest extends TestCase
{
    private function makeRule(
        ?DateTimeImmutable $startsAt = null,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
    ): DiscountRule {
        return DiscountRule::create(
            tenantId: 1,
            name: '10% off',
            description: null,
            discountType: DiscountType::Percentage,
            discountValue: 10,
            priority: new DiscountPriority(10),
            stackability: Stackability::Stackable,
            conditions: [],
            startsAt: $startsAt ?? new DateTimeImmutable('-1 day'),
            expiresAt: $expiresAt,
            maxUses: $maxUses,
        );
    }

    public function test_create_startsActiveWithZeroUsedCount(): void
    {
        $rule = $this->makeRule();

        $this->assertTrue($rule->isActive());
        $this->assertSame(0, $rule->usedCount());
    }

    public function test_isCurrentlyActive_withinWindow_isTrue(): void
    {
        $rule = $this->makeRule();

        $this->assertTrue($rule->isCurrentlyActive(new DateTimeImmutable()));
    }

    public function test_isCurrentlyActive_beforeStartsAt_isFalse(): void
    {
        $rule = $this->makeRule(startsAt: new DateTimeImmutable('+1 day'));

        $this->assertFalse($rule->isCurrentlyActive(new DateTimeImmutable()));
    }

    public function test_isCurrentlyActive_afterExpiresAt_isFalse(): void
    {
        $rule = $this->makeRule(expiresAt: new DateTimeImmutable('-1 hour'));

        $this->assertFalse($rule->isCurrentlyActive(new DateTimeImmutable()));
    }

    public function test_isCurrentlyActive_whenDeactivated_isFalse(): void
    {
        $rule = $this->makeRule();
        $rule->update('10% off', null, 10, new DiscountPriority(10), Stackability::Stackable, $rule->startsAt(), null, isActive: false);

        $this->assertFalse($rule->isCurrentlyActive(new DateTimeImmutable()));
    }

    public function test_isCurrentlyActive_afterMaxUsesReached_isFalse(): void
    {
        $rule = $this->makeRule(maxUses: 1);
        $rule->recordUsage();

        $this->assertTrue($rule->hasReachedMaxUses());
        $this->assertFalse($rule->isCurrentlyActive(new DateTimeImmutable()));
    }

    public function test_recordUsage_incrementsUsedCount(): void
    {
        $rule = $this->makeRule();

        $rule->recordUsage();
        $rule->recordUsage();

        $this->assertSame(2, $rule->usedCount());
    }

    public function test_update_leavesConditionsAndDiscountTypeUntouched(): void
    {
        $rule = $this->makeRule();

        $rule->update('New name', 'New description', 20, new DiscountPriority(5), Stackability::Exclusive, $rule->startsAt(), null, true);

        $this->assertSame('New name', $rule->name());
        $this->assertSame(20, $rule->discountValue());
        $this->assertSame(DiscountType::Percentage, $rule->discountType()); // untouched
        $this->assertSame([], $rule->conditions()); // untouched
    }
}
