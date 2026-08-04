<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;

/**
 * One condition a DiscountRule must satisfy (AND-combined with every
 * other condition on the same rule — rule §د.4). No `id`/`discountRuleId`
 * property on the Domain entity, the same HANDOFF gotcha #10 shape every
 * other child-of-a-parent entity in this codebase has —
 * `discount_rule_conditions` itself still has a real `id` primary key.
 * `value` is whatever shape `DiscountCondition`'s own case implies
 * (`MinQuantity`/`MaxQuantity`: int; `CategoryIds`/`ProductIds`:
 * `list<int>`; `CustomerGroup`: string; `TieredThresholds`:
 * `list<array{min_subtotal: int, percentage: int}>` — see
 * `DiscountCalculator`'s own docblock), already JSON-decoded by the time
 * it reaches this entity.
 */
final class DiscountRuleCondition
{
    public function __construct(
        private readonly DiscountCondition $type,
        private readonly mixed $value,
    ) {
    }

    public function type(): DiscountCondition
    {
        return $this->type;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
