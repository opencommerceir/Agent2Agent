<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * Added unprompted (HANDOFF §3 pattern #12) — the request's own rule §د.6
 * asks for "proration (محاسبه اختلاف قیمت)" but names no dedicated class
 * for it; UpgradeSubscriptionAction needs a real, unit-testable formula
 * rather than inline date/money math, the same reasoning every other
 * pure-formula Domain Service in this module already gives.
 *
 * Formula: both the old plan's remaining credit and the new plan's own
 * prorated cost are computed as `price * remainingDays / totalPeriodDays`
 * (integer floor division on cents, HANDOFF gotcha #4 — money is always an
 * integer), and the invoice amount is `newProratedCost - oldCredit`,
 * floored at 0. A downgrade whose credit exceeds the new plan's own
 * prorated cost simply charges $0 this stage — there is no
 * credit-carry-forward or refund mechanism yet, a documented
 * simplification the same shape `CustomerLifetimeValue`'s own honestly-
 * scoped-down formula already carries (§7.18/§8.52).
 */
final class SubscriptionProrationCalculator
{
    public function calculate(
        Money $oldPrice,
        Money $newPrice,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        DateTimeImmutable $now,
    ): int {
        $totalDays = max(1, $periodStart->diff($periodEnd)->days);
        $remainingDays = $now >= $periodEnd ? 0 : max(0, $now->diff($periodEnd)->days);

        $credit = (int) floor($oldPrice->amount() * $remainingDays / $totalDays);
        $newProratedCost = (int) floor($newPrice->amount() * $remainingDays / $totalDays);

        return max(0, $newProratedCost - $credit);
    }
}
