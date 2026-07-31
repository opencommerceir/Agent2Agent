<?php

namespace App\Modules\Loyalty\Domain\Entities;

use App\Modules\Loyalty\Domain\Exceptions\InsufficientPointsException;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use DateTimeImmutable;

/**
 * One Customer's running points balance for one tenant — exactly one per
 * (tenant, customer) pair (rule §d.2, enforced by
 * `loyalty_accounts.unique(tenant_id, customer_id)` and
 * CreateLoyaltyAccountAction's own pre-check).
 *
 * `current_balance` is maintained directly by earn()/redeem()/expire()/
 * adjust() as each happens — never recomputed from the other two totals
 * on read. This still satisfies rule §d.4
 * ("current_balance = total_points_earned - total_points_redeemed - expired_points")
 * exactly, because those are the only four operations that ever touch
 * `current_balance`: earn() and redeem() move `current_balance` in
 * lock-step with the running total they also update, while expire()
 * subtracts from `current_balance` alone (there is deliberately no
 * `total_points_expired` column in the requested schema — see
 * PointTransactionRepositoryInterface::findExpirable()'s docblock for
 * how an already-expired batch is recognized without one).
 */
final class LoyaltyAccount
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $customerId,
        private Points $totalPointsEarned,
        private Points $totalPointsRedeemed,
        private Points $currentBalance,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function open(int $tenantId, int $customerId): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            customerId: $customerId,
            totalPointsEarned: new Points(0),
            totalPointsRedeemed: new Points(0),
            currentBalance: new Points(0),
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Covers both `earn` (from a purchase) and `bonus` (a manual grant) —
     * both count identically toward `total_points_earned` and
     * `current_balance`; only the PointTransaction ledger entry
     * distinguishes which one happened (EarnPointsAction's own docblock).
     */
    public function earn(Points $points): void
    {
        $this->totalPointsEarned = new Points($this->totalPointsEarned->value() + $points->value());
        $this->currentBalance = new Points($this->currentBalance->value() + $points->value());
    }

    public function redeem(Points $points): void
    {
        if ($points->value() > $this->currentBalance->value()) {
            throw new InsufficientPointsException(
                "Only {$this->currentBalance->value()} point(s) available, requested {$points->value()}."
            );
        }

        $this->totalPointsRedeemed = new Points($this->totalPointsRedeemed->value() + $points->value());
        $this->currentBalance = new Points($this->currentBalance->value() - $points->value());
    }

    /**
     * Deliberately does not touch `total_points_redeemed` — expiring is
     * not redeeming (ExpirePointsAction's own docblock). Clamps to
     * whatever balance genuinely remains rather than throwing, since
     * points already spent via redeem() before their expiry date must
     * never make this go negative.
     */
    public function expire(Points $points): void
    {
        $expiring = min($points->value(), $this->currentBalance->value());
        $this->currentBalance = new Points($this->currentBalance->value() - $expiring);
    }

    /**
     * A manual correction — the one operation that may move
     * `current_balance` in either direction without touching either
     * running total, for the `adjust` TransactionType (e.g. a support
     * agent fixing a bookkeeping error). Clamped at zero, same reasoning
     * Commerce's Inventory::restore()/commit() clamp at zero.
     */
    public function adjust(int $delta): void
    {
        $this->currentBalance = new Points(max(0, $this->currentBalance->value() + $delta));
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

    public function totalPointsEarned(): Points
    {
        return $this->totalPointsEarned;
    }

    public function totalPointsRedeemed(): Points
    {
        return $this->totalPointsRedeemed;
    }

    public function currentBalance(): Points
    {
        return $this->currentBalance;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
