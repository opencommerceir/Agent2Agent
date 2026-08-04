<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;
use App\Modules\Commerce\Domain\Services\DiscountRuleEvaluator;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DiscountRuleEvaluatorTest extends TestCase
{
    private DiscountRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new DiscountRuleEvaluator();
    }

    private function makeRule(
        DiscountPriority $priority,
        Stackability $stackability,
        array $conditions = [],
    ): DiscountRule {
        return DiscountRule::create(
            tenantId: 1,
            name: 'Rule',
            description: null,
            discountType: DiscountType::Percentage,
            discountValue: 10,
            priority: $priority,
            stackability: $stackability,
            conditions: $conditions,
            startsAt: new DateTimeImmutable('-1 day'),
        );
    }

    private function context(int $subtotal = 8000, int $quantity = 3): DiscountEvaluationContext
    {
        return new DiscountEvaluationContext(
            subtotalAmount: $subtotal,
            currency: 'USD',
            items: [
                ['productId' => 1, 'categoryId' => 10, 'quantity' => $quantity, 'unitPriceAmount' => intdiv($subtotal, $quantity)],
            ],
        );
    }

    public function test_evaluate_ruleWithNoConditions_isTrueWhenActive(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable);

        $this->assertTrue($this->evaluator->evaluate($rule, $this->context(), new DateTimeImmutable()));
    }

    public function test_evaluate_expiredRule_isFalse(): void
    {
        $rule = DiscountRule::create(
            tenantId: 1,
            name: 'Expired',
            description: null,
            discountType: DiscountType::Percentage,
            discountValue: 10,
            priority: new DiscountPriority(1),
            stackability: Stackability::Stackable,
            conditions: [],
            startsAt: new DateTimeImmutable('-2 days'),
            expiresAt: new DateTimeImmutable('-1 day'),
        );

        $this->assertFalse($this->evaluator->evaluate($rule, $this->context(), new DateTimeImmutable()));
    }

    public function test_evaluate_minQuantityConditionNotMet_isFalse(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 5),
        ]);

        $this->assertFalse($this->evaluator->evaluate($rule, $this->context(quantity: 3), new DateTimeImmutable()));
    }

    public function test_evaluate_minQuantityConditionMet_isTrue(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 2),
        ]);

        $this->assertTrue($this->evaluator->evaluate($rule, $this->context(quantity: 3), new DateTimeImmutable()));
    }

    public function test_evaluate_categoryIdsConditionMatchingItem_isTrue(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::CategoryIds, [10, 20]),
        ]);

        $this->assertTrue($this->evaluator->evaluate($rule, $this->context(), new DateTimeImmutable()));
    }

    public function test_evaluate_categoryIdsConditionNoMatchingItem_isFalse(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::CategoryIds, [999]),
        ]);

        $this->assertFalse($this->evaluator->evaluate($rule, $this->context(), new DateTimeImmutable()));
    }

    public function test_evaluate_minSubtotalConditionNotMet_isFalse(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinSubtotal, 10000),
        ]);

        $this->assertFalse($this->evaluator->evaluate($rule, $this->context(subtotal: 8000), new DateTimeImmutable()));
    }

    public function test_evaluate_minSubtotalConditionMet_isTrue(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinSubtotal, 5000),
        ]);

        $this->assertTrue($this->evaluator->evaluate($rule, $this->context(subtotal: 8000), new DateTimeImmutable()));
    }

    public function test_evaluate_multipleConditions_requiresAllToPass_andLogic(): void
    {
        $rule = $this->makeRule(new DiscountPriority(1), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 2),
            new DiscountRuleCondition(DiscountCondition::CategoryIds, [999]), // never matches
        ]);

        $this->assertFalse($this->evaluator->evaluate($rule, $this->context(), new DateTimeImmutable()));
    }

    /**
     * The literal Phase 5 Stage 4 (§7.24) end-to-end scenario: Rule A
     * (stackable, priority 10), Rule B (exclusive, priority 5), Rule C
     * (stackable, priority 1) — A and C apply together, B is skipped
     * because it can't join a set already anchored by a Stackable rule.
     */
    public function test_selectApplicableRules_stackableRulesCombine_exclusiveRuleExcluded(): void
    {
        $ruleA = $this->makeRule(new DiscountPriority(10), Stackability::Stackable);
        $ruleB = $this->makeRule(new DiscountPriority(5), Stackability::Exclusive);
        $ruleC = $this->makeRule(new DiscountPriority(1), Stackability::Stackable);

        $selected = $this->evaluator->selectApplicableRules([$ruleA, $ruleB, $ruleC], $this->context(), new DateTimeImmutable());

        $this->assertSame([$ruleA, $ruleC], $selected);
    }

    public function test_selectApplicableRules_highestPriorityExclusive_blocksEverythingElse(): void
    {
        $ruleA = $this->makeRule(new DiscountPriority(20), Stackability::Exclusive);
        $ruleB = $this->makeRule(new DiscountPriority(10), Stackability::Stackable);
        $ruleC = $this->makeRule(new DiscountPriority(1), Stackability::Stackable);

        $selected = $this->evaluator->selectApplicableRules([$ruleA, $ruleB, $ruleC], $this->context(), new DateTimeImmutable());

        $this->assertSame([$ruleA], $selected);
    }

    public function test_selectApplicableRules_twoExclusiveRules_combineWithEachOther(): void
    {
        $ruleA = $this->makeRule(new DiscountPriority(10), Stackability::Exclusive);
        $ruleB = $this->makeRule(new DiscountPriority(5), Stackability::Exclusive);

        $selected = $this->evaluator->selectApplicableRules([$ruleA, $ruleB], $this->context(), new DateTimeImmutable());

        $this->assertSame([$ruleA, $ruleB], $selected);
    }

    public function test_selectApplicableRules_couponOnlyRule_neverAutomaticallySelected(): void
    {
        $ruleA = $this->makeRule(new DiscountPriority(100), Stackability::CouponOnly);

        $selected = $this->evaluator->selectApplicableRules([$ruleA], $this->context(), new DateTimeImmutable());

        $this->assertSame([], $selected);
    }

    public function test_selectApplicableRules_ineligibleRule_isNeverSelected(): void
    {
        $ruleA = $this->makeRule(new DiscountPriority(10), Stackability::Stackable, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 999),
        ]);

        $selected = $this->evaluator->selectApplicableRules([$ruleA], $this->context(), new DateTimeImmutable());

        $this->assertSame([], $selected);
    }
}
