<?php

namespace App\Modules\Loyalty\Domain\Entities;

use App\Modules\Loyalty\Domain\ValueObjects\Points;
use DateTimeImmutable;

/**
 * One record of a Customer spending points on a Reward. Not in the
 * original request's Repository list — the request names a
 * `redemptions` table and this Entity but only 3 Repository interfaces
 * for the whole module, the same gap Workflows' `WorkflowLog` had
 * (HANDOFF §3 item 12). Per that stage's own precedent (a Repository
 * interface owns its child records — WorkflowRepositoryInterface owns
 * WorkflowLog, CRM's TicketRepositoryInterface owns TicketComment), the
 * natural owner here is `LoyaltyAccountRepositoryInterface::saveRedemption()`/
 * `listRedemptions()` — a Redemption's whole meaning is "this account
 * spent points", so it belongs to the account's own Repository, not a
 * 4th interface.
 *
 * `status` is a plain string, not a dedicated Value Object (same choice
 * Workflows' WorkflowLog makes for its own `status` field) — every
 * Redemption this stage creates is `completed` immediately
 * (RedeemPointsAction is a durable, synchronous spend, not a multi-step
 * flow); `pending`/`cancelled` are real, modeled states with no code
 * path that reaches them yet, the same "modeled but not all reachable"
 * shape RewardType's FreeProduct/FreeShipping cases have.
 */
final class Redemption
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $loyaltyAccountId,
        private readonly int $rewardId,
        private readonly Points $pointsUsed,
        private readonly string $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function complete(int $tenantId, int $loyaltyAccountId, int $rewardId, Points $pointsUsed): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            loyaltyAccountId: $loyaltyAccountId,
            rewardId: $rewardId,
            pointsUsed: $pointsUsed,
            status: 'completed',
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function loyaltyAccountId(): int
    {
        return $this->loyaltyAccountId;
    }

    public function rewardId(): int
    {
        return $this->rewardId;
    }

    public function pointsUsed(): Points
    {
        return $this->pointsUsed;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
