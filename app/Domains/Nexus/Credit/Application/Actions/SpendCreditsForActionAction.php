<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;

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
    ) {
    }

    /**
     * Throws InsufficientCreditException (via DeductCreditsAction ->
     * CreditBalance::debit()) when the Business can't cover the cost —
     * callers let it propagate so MCPExceptionHandler turns it into a
     * clean 409.
     */
    public function execute(int $businessId, string $actionKey, ?int $relatedId = null): ?CreditBalanceData
    {
        $costs = config('nexus.platform.credit.action_costs', []);
        $cost = (int) ($costs[$actionKey] ?? 0);

        if ($cost <= 0) {
            return null;
        }

        return $this->deductCredits->execute($businessId, $cost, $actionKey, $relatedId);
    }
}
