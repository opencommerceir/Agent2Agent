<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use DateTimeImmutable;

/**
 * Pure — only combines a Subscription's own already-known period end with
 * SubscriptionBillingCalculator's interval math, the same "Domain Service
 * only combines data already handed to it" shape WorkflowEvaluator/
 * NearestWarehouseFinder already establish. Never touches a Repository or
 * PaymentGatewayInterface itself (both Application-layer concerns) —
 * ProcessSubscriptionRenewalAction is the one place charging/persistence
 * actually happens.
 */
final class SubscriptionRenewalService
{
    public function __construct(
        private readonly SubscriptionBillingCalculator $calculator,
    ) {
    }

    /**
     * The new period a renewal should move a Subscription into. Starts
     * from the later of "now" and the current period end, so a renewal run
     * late (the scheduler missed a day, for example) never shortens the
     * Customer's own next period.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public function nextPeriod(Subscription $subscription, BillingCycle $cycle, DateTimeImmutable $now): array
    {
        $start = $subscription->currentPeriodEnd() > $now ? $subscription->currentPeriodEnd() : $now;

        return [
            'start' => $start,
            'end' => $this->calculator->nextPeriodEnd($start, $cycle),
        ];
    }
}
