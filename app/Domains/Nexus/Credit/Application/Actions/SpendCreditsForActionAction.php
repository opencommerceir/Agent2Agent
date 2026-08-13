<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;

/**
 * The roadmap's "CostGate" — "قبل از هر LLM call یا Agent action، اعتبار
 * را چک کن... در صورت عدم موجودی: رد کردن درخواست" (docs/nexus-roadmap.md,
 * Phase 3). Lives inside each capability's own Action, never in Core's
 * AbstractMCPGatewayController/CapabilityExecutionService — Decision 007
 * forbids business logic in Core — the same shape AcceptDealAction's
 * inline authority_limits check already established for a different gate.
 *
 * Reads its price list from config('nexus.platform.credit.action_costs');
 * a $0 (or missing) key means the action is free, not an error — read-only
 * capabilities (status/list/poll) simply never call this at all, and a
 * future action with no configured price is free by default rather than
 * accidentally blocking every caller.
 *
 * Looks the price list array up in one dot-path (`...action_costs`), then
 * indexes into it with a plain (non-dot) array access — every
 * `$actionKey` here is itself a dotted capability name
 * (`nexus.marketplace.search`), and config()'s own dot-notation would
 * wrongly explode that into nested segments if passed the whole path in
 * one string (`config("...action_costs.{$actionKey}")` silently always
 * misses).
 */
final class SpendCreditsForActionAction
{
    public function __construct(
        private readonly DeductCreditsAction $deductCredits,
        private readonly DeductFromHoldingPoolAction $deductFromPool,
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    /**
     * Throws InsufficientCreditException (via DeductCreditsAction ->
     * CreditBalance::debit(), or DeductFromHoldingPoolAction ->
     * HoldingCreditPool::debit()) when the balance being charged can't
     * cover the cost — callers let it propagate so MCPExceptionHandler
     * turns it into a clean 409 either way.
     *
     * Phase 7/M2 — before falling back to the Business's own balance,
     * checks whether it's a member (parent or Active subsidiary) of a
     * Holding with pooling enabled; if so, the Holding's shared pool is
     * charged instead. No Holding/pooling involved is byte-for-byte
     * today's behavior — this check is a read-only lookup, same as any
     * other Action that injects another domain's repository interface to
     * validate against (e.g. CreateCoalitionAction reading
     * BusinessRepositoryInterface), not a violation of Inter-Module
     * Communication's write-side rule.
     */
    public function execute(int $businessId, string $actionKey, ?int $relatedId = null): ?CreditBalanceData
    {
        $costs = config('nexus.platform.credit.action_costs', []);
        $cost = (int) ($costs[$actionKey] ?? 0);

        if ($cost <= 0) {
            return null;
        }

        $poolingHoldingId = $this->resolvePoolingHoldingId($businessId);

        if ($poolingHoldingId !== null) {
            $this->deductFromPool->execute($poolingHoldingId, $cost, $actionKey, $businessId, $relatedId);

            return null;
        }

        return $this->deductCredits->execute($businessId, $cost, $actionKey, $relatedId);
    }

    private function resolvePoolingHoldingId(int $businessId): ?int
    {
        $asParent = $this->holdings->findByParentBusinessId($businessId);

        if ($asParent && $asParent->creditPoolingEnabled()) {
            return $asParent->id();
        }

        $membership = $this->subsidiaries->findActiveOrInvitedByBusinessId($businessId);

        if (! $membership || $membership->status() !== SubsidiaryStatus::Active) {
            return null;
        }

        $holding = $this->holdings->findById($membership->holdingId());

        return $holding && $holding->creditPoolingEnabled() ? $holding->id() : null;
    }
}
