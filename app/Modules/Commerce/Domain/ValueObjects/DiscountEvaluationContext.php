<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * Everything `DiscountRuleEvaluator`/`DiscountCalculator` need to judge
 * one DiscountRule against one Cart (or, at real checkout, one soon-to-be
 * Order) — built by the calling Action from a real Cart's own items plus
 * a bounded per-item Product lookup for `categoryId` (the same "small,
 * bounded per-item Repository lookup, not a batch method" precedent
 * Reporting's Top Products/Customers name resolution already establishes,
 * §7.11), never assembled by either Domain Service itself. Neither
 * Service may query a Repository — this VO is the entire boundary
 * between "fetch the data" (an Action's job) and "judge the data" (a
 * pure Domain Service's job), the same split `NearestWarehouseFinder`'s
 * own caller-built `$candidates` array already establishes (§7.22).
 */
final class DiscountEvaluationContext
{
    /**
     * @param list<array{productId: int, categoryId: ?int, quantity: int, unitPriceAmount: int}> $items
     */
    public function __construct(
        public readonly int $subtotalAmount,
        public readonly string $currency,
        public readonly array $items,
        public readonly ?string $customerGroup = null,
    ) {
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (array $item) => $item['quantity'], $this->items));
    }
}
