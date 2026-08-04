<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;

/**
 * Pure, framework-free — the same shape `WorkflowEvaluator`/`PricingService`
 * already establish: only combines a DiscountRule and a caller-built
 * `DiscountEvaluationContext`, never queries a Repository itself.
 *
 * Two responsibilities, both "evaluation": `evaluate()` judges one rule in
 * isolation (is it currently active, do all its conditions pass — AND
 * logic, rule §д.4); `selectApplicableRules()` resolves *which* of several
 * eligible rules actually apply together, given priority order and
 * Stackability (rule §д.3) — see that method's own docblock for the exact
 * combination matrix.
 */
final class DiscountRuleEvaluator
{
    public function evaluate(DiscountRule $rule, DiscountEvaluationContext $context, DateTimeImmutable $now): bool
    {
        if (! $rule->isCurrentlyActive($now)) {
            return false;
        }

        foreach ($rule->conditions() as $condition) {
            if (! $this->conditionPasses($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Highest priority first. `CouponOnly` rules are never eligible for
     * automatic selection at all (Stackability's own docblock — they only
     * ever apply through an explicit, linked Coupon). Once the
     * highest-priority eligible rule is selected, only rules sharing that
     * *same* Stackability value can join it — `Stackable` combines with
     * `Stackable`, `Exclusive` combines with `Exclusive` (rule §д.3's own
     * literal text), but the two never mix; there is no third case to
     * consider since `CouponOnly` was already filtered out above.
     *
     * @param list<DiscountRule> $rules
     * @return list<DiscountRule>
     */
    public function selectApplicableRules(array $rules, DiscountEvaluationContext $context, DateTimeImmutable $now): array
    {
        $eligible = array_values(array_filter(
            $rules,
            fn (DiscountRule $rule) => $rule->stackability() !== Stackability::CouponOnly
                && $this->evaluate($rule, $context, $now),
        ));

        usort($eligible, fn (DiscountRule $a, DiscountRule $b) => $b->priority()->value() <=> $a->priority()->value());

        $selected = [];
        $selectedStackability = null;

        foreach ($eligible as $rule) {
            if ($selectedStackability === null || $rule->stackability() === $selectedStackability) {
                $selected[] = $rule;
                $selectedStackability = $rule->stackability();
            }
        }

        return $selected;
    }

    private function conditionPasses(DiscountRuleCondition $condition, DiscountEvaluationContext $context): bool
    {
        return match ($condition->type()) {
            DiscountCondition::MinQuantity => $context->totalQuantity() >= (int) $condition->value(),
            DiscountCondition::MaxQuantity => $context->totalQuantity() <= (int) $condition->value(),
            DiscountCondition::MinSubtotal => $context->subtotalAmount >= (int) $condition->value(),
            DiscountCondition::CategoryIds => $this->anyItemMatches(
                $context,
                fn (array $item) => $item['categoryId'] !== null && in_array($item['categoryId'], $condition->value(), true),
            ),
            DiscountCondition::ProductIds => $this->anyItemMatches(
                $context,
                fn (array $item) => in_array($item['productId'], $condition->value(), true),
            ),
            // No CustomerGroup/segmentation concept exists anywhere in
            // Commerce/CRM yet (a documented gap, §8) — this condition
            // type is modeled and only ever passes when the context was
            // explicitly given a matching group, never inferred.
            DiscountCondition::CustomerGroup => $context->customerGroup !== null
                && $context->customerGroup === $condition->value(),
            // Not a pass/fail gate at all — TieredThresholds only shapes
            // DiscountCalculator's own tier selection for a Tiered rule;
            // its presence/absence as a *condition* never excludes a rule.
            DiscountCondition::TieredThresholds => true,
        };
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     */
    private function anyItemMatches(DiscountEvaluationContext $context, callable $predicate): bool
    {
        foreach ($context->items as $item) {
            if ($predicate($item)) {
                return true;
            }
        }

        return false;
    }
}
