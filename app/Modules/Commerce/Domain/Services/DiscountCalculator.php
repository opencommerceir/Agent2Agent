<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;

/**
 * Pure, framework-free — the single formula owner for "how much does one
 * DiscountRule actually discount," the same shape `Coupon::calculateDiscount()`
 * already has for a plain Coupon (rule §e). Assumes the rule has *already*
 * been judged eligible by `DiscountRuleEvaluator::evaluate()` — this class
 * only computes an amount, it never re-checks conditions itself. Every
 * branch clamps to `$context->subtotalAmount` so a discount can never
 * exceed what's actually being bought (the same "never let Total go
 * negative" guard `Coupon::calculateDiscount()`'s own FixedAmount branch
 * already has).
 *
 * Buy X Get Y (rule §e.3's own literal pseudocode: a flat, non-repeating
 * grant once the quantity threshold is met, not "for every complete
 * X+Y group") grants `discountValue` units free, always the *cheapest*
 * matching units in the Cart — the buy-quantity threshold itself is
 * `DiscountRuleEvaluator`'s own `MinQuantity` condition check, already
 * satisfied by the time this method runs (a rule that failed it was never
 * selected).
 *
 * Tiered reads an optional `TieredThresholds` condition (rule §д's own
 * schema has no dedicated tier columns — see `DiscountCondition::TieredThresholds`'s
 * own docblock for why this condition type was added unprompted): a
 * `list<array{min_subtotal: int, percentage: int}>`, picks the highest
 * `percentage` among tiers whose `min_subtotal` the subtotal meets or
 * exceeds. A Tiered rule with no such condition falls back to treating
 * `discountValue` itself as one flat percentage, never throwing.
 */
final class DiscountCalculator
{
    public function calculate(DiscountRule $rule, DiscountEvaluationContext $context): Money
    {
        $amount = match ($rule->discountType()) {
            DiscountType::Percentage => intdiv($context->subtotalAmount * $rule->discountValue(), 100),
            DiscountType::FixedAmount => min($rule->discountValue(), $context->subtotalAmount),
            DiscountType::BuyXGetY => $this->calculateBuyXGetY($rule, $context),
            DiscountType::Tiered => $this->calculateTiered($rule, $context),
        };

        return Money::fromAmount(min($amount, $context->subtotalAmount), $context->currency);
    }

    private function calculateBuyXGetY(DiscountRule $rule, DiscountEvaluationContext $context): int
    {
        $getQuantity = $rule->discountValue();

        if ($getQuantity <= 0 || $context->items === []) {
            return 0;
        }

        $unitPrices = [];

        foreach ($context->items as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $unitPrices[] = $item['unitPriceAmount'];
            }
        }

        sort($unitPrices);

        return array_sum(array_slice($unitPrices, 0, min($getQuantity, count($unitPrices))));
    }

    private function calculateTiered(DiscountRule $rule, DiscountEvaluationContext $context): int
    {
        $tiers = null;

        foreach ($rule->conditions() as $condition) {
            if ($condition->type() === DiscountCondition::TieredThresholds) {
                $tiers = $condition->value();

                break;
            }
        }

        if ($tiers === null) {
            return intdiv($context->subtotalAmount * $rule->discountValue(), 100);
        }

        $bestPercentage = 0;

        foreach ($tiers as $tier) {
            if ($context->subtotalAmount >= $tier['min_subtotal'] && $tier['percentage'] > $bestPercentage) {
                $bestPercentage = $tier['percentage'];
            }
        }

        return intdiv($context->subtotalAmount * $bestPercentage, 100);
    }
}
