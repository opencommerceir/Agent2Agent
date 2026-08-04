<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\Exceptions\InvalidSubscriptionStateException;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;

/**
 * One Customer's subscription to one SubscriptionPlan. `customerId` and
 * `subscriptionPlanId` are plain ints, not object references — the same
 * "two aggregates meet only through ids" shape every other entity pointing
 * at a sibling aggregate in this module already uses (Order -> Customer,
 * CartItem -> Product).
 *
 * State machine (rule §ه): `Cancelled`/`Expired` are the only true
 * terminal states. `Active` is reachable from `Trial` (first successful
 * charge or trial simply ending) and from `PastDue` (a recovered
 * payment) — both go through `renew()`; `PastDue -> Active` with no new
 * period (a bare retry succeeding, not a fresh billing cycle) goes through
 * `reactivate()` instead. `Paused` is reachable only from `Active` — the
 * request's own lifecycle narrative always describes pausing an active
 * subscription, never a trial or already-past-due one. `Expired` is
 * modeled (SubscriptionStatus's own docblock) but no method on this entity
 * ever transitions into it this stage.
 *
 * `cancelAtPeriodEnd` is a flag, not a status — scheduling a
 * cancel-for-later doesn't change `status` at all (mirrors
 * `Order::changeStatus()`'s "an in-flight state stays in flight until its
 * own trigger fires" shape); `cancelAtPeriodEndReached()` is the one
 * ProcessSubscriptionRenewalAction calls once the period actually ends,
 * turning the flag into a real `Cancelled` transition.
 */
final class Subscription
{
    /**
     * @var array<string, list<SubscriptionStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'trial' => [SubscriptionStatus::Active, SubscriptionStatus::Cancelled],
        'active' => [SubscriptionStatus::Paused, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled],
        'paused' => [SubscriptionStatus::Active, SubscriptionStatus::Cancelled],
        'past_due' => [SubscriptionStatus::Active, SubscriptionStatus::Cancelled],
        'cancelled' => [],
        'expired' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $customerId,
        private int $subscriptionPlanId,
        private SubscriptionStatus $status,
        private DateTimeImmutable $currentPeriodStart,
        private DateTimeImmutable $currentPeriodEnd,
        private readonly ?DateTimeImmutable $trialStart,
        private ?DateTimeImmutable $trialEnd,
        private ?DateTimeImmutable $pausedAt,
        private ?DateTimeImmutable $cancelledAt,
        private bool $cancelAtPeriodEnd,
        private readonly ?string $paymentMethodId,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function startTrial(
        int $tenantId,
        int $customerId,
        int $subscriptionPlanId,
        int $trialDays,
        ?string $paymentMethodId,
    ): self {
        $now = new DateTimeImmutable();
        $trialEnd = $now->modify("+{$trialDays} days");

        return new self(
            id: null,
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $subscriptionPlanId,
            status: SubscriptionStatus::Trial,
            currentPeriodStart: $now,
            currentPeriodEnd: $trialEnd,
            trialStart: $now,
            trialEnd: $trialEnd,
            pausedAt: null,
            cancelledAt: null,
            cancelAtPeriodEnd: false,
            paymentMethodId: $paymentMethodId,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function startActive(
        int $tenantId,
        int $customerId,
        int $subscriptionPlanId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?string $paymentMethodId,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $subscriptionPlanId,
            status: SubscriptionStatus::Active,
            currentPeriodStart: $periodStart,
            currentPeriodEnd: $periodEnd,
            trialStart: null,
            trialEnd: null,
            pausedAt: null,
            cancelledAt: null,
            cancelAtPeriodEnd: false,
            paymentMethodId: $paymentMethodId,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function pause(): void
    {
        $this->transitionTo(SubscriptionStatus::Paused);
        $this->pausedAt = new DateTimeImmutable();
    }

    /**
     * Extends `currentPeriodEnd` by exactly however long the Subscription
     * sat paused (rule §ه.3: "Resume: status = active, extend period by
     * pause duration") — a paused Customer never loses paid-for time.
     */
    public function resume(): void
    {
        if ($this->pausedAt === null) {
            throw new InvalidSubscriptionStateException('Cannot resume a Subscription that was never paused.');
        }

        $pauseDuration = $this->pausedAt->diff(new DateTimeImmutable());

        $this->transitionTo(SubscriptionStatus::Active);
        $this->currentPeriodEnd = $this->currentPeriodEnd->add($pauseDuration);
        $this->pausedAt = null;
    }

    public function cancelImmediately(): void
    {
        $this->transitionTo(SubscriptionStatus::Cancelled);
        $this->cancelledAt = new DateTimeImmutable();
        $this->cancelAtPeriodEnd = false;
    }

    public function scheduleCancelAtPeriodEnd(): void
    {
        if ($this->status === SubscriptionStatus::Cancelled || $this->status === SubscriptionStatus::Expired) {
            throw new InvalidSubscriptionStateException(
                "Cannot schedule a cancellation for a Subscription already [{$this->status->value}]."
            );
        }

        $this->cancelAtPeriodEnd = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Called by ProcessSubscriptionRenewalAction once the current period
     * actually ends and `cancelAtPeriodEnd` is set — the scheduled flag
     * becoming a real, terminal transition, no new invoice ever generated.
     */
    public function cancelAtPeriodEndReached(): void
    {
        $this->transitionTo(SubscriptionStatus::Cancelled);
        $this->cancelledAt = new DateTimeImmutable();
        $this->cancelAtPeriodEnd = false;
    }

    /**
     * Reached after a SubscriptionInvoice exhausts its 3 retries (rule
     * §د.5) — never called directly by any renewal/retry success path.
     * Tolerates a PastDue -> PastDue self-transition deliberately, the
     * same "same-status no-op" tolerance renew() already has for
     * Active -> Active: a Subscription that went PastDue immediately on
     * its very first charge (CreateSubscriptionAction's own no-retry-grace
     * policy) still has that same invoice picked up by
     * SubscriptionInvoiceRepositoryInterface::findDueForRetry() and
     * auto-retried — a real declined card can still recover on a later
     * attempt even after the Subscription is already flagged PastDue, and
     * that 3rd/exhausting retry failure must not throw just because the
     * Subscription happened to already be sitting in the same status.
     */
    public function markPastDue(): void
    {
        if ($this->status !== SubscriptionStatus::PastDue) {
            $this->transitionTo(SubscriptionStatus::PastDue);
        }
    }

    /**
     * A retry recovering payment on the *same* invoice — no new billing
     * period, unlike renew(). PastDue -> Active only.
     */
    public function reactivate(): void
    {
        $this->transitionTo(SubscriptionStatus::Active);
    }

    /**
     * Advances the billing period and (re)confirms Active status. Tolerates
     * an Active -> Active self-transition deliberately — the ordinary
     * renewal case — the same "same-status no-op inside a fulfillment
     * pipeline" tolerance Order::changeStatus() already established;
     * Trial -> Active and PastDue -> Active both still go through the
     * strict ALLOWED_TRANSITIONS check.
     */
    public function renew(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): void
    {
        if ($this->status !== SubscriptionStatus::Active) {
            $this->transitionTo(SubscriptionStatus::Active);
        }

        $this->currentPeriodStart = $periodStart;
        $this->currentPeriodEnd = $periodEnd;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * The in-place plan swap Upgrade/Downgrade uses (HANDOFF §7.25) —
     * period bounds are untouched; only the plan this Subscription bills
     * against going forward changes. A simplification from the request's
     * own "create a new subscription" lifecycle prose — see
     * UpgradeSubscriptionAction's own docblock for the full reasoning.
     */
    public function changePlan(int $newSubscriptionPlanId): void
    {
        $this->subscriptionPlanId = $newSubscriptionPlanId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isDueForRenewal(DateTimeImmutable $now): bool
    {
        return in_array($this->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)
            && $this->currentPeriodEnd <= $now;
    }

    private function transitionTo(SubscriptionStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidSubscriptionStateException(
                "Subscription cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function subscriptionPlanId(): int
    {
        return $this->subscriptionPlanId;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function currentPeriodStart(): DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function currentPeriodEnd(): DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function trialStart(): ?DateTimeImmutable
    {
        return $this->trialStart;
    }

    public function trialEnd(): ?DateTimeImmutable
    {
        return $this->trialEnd;
    }

    public function pausedAt(): ?DateTimeImmutable
    {
        return $this->pausedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function paymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
