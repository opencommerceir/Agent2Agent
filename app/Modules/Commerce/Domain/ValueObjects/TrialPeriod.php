<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Wraps a SubscriptionPlan's own trial length. The request's own VO list
 * named this "days, features" — but the request's own DB schema has
 * exactly one `features` JSON column, living on `subscription_plans`
 * itself, not a second trial-scoped features concept; inventing one here
 * would mean two different "features" ideas for a schema that only ever
 * defines one. `SubscriptionPlan::features()` carries it instead — this VO
 * stays to just the day count, the one thing genuinely trial-specific.
 */
final class TrialPeriod
{
    public function __construct(
        private readonly int $days,
    ) {
        if ($days < 0) {
            throw new InvalidArgumentException("TrialPeriod days cannot be negative, got [{$days}].");
        }
    }

    public function days(): int
    {
        return $this->days;
    }

    public function hasTrial(): bool
    {
        return $this->days > 0;
    }
}
