<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Contract\Domain\Entities\Contract;
use App\Domains\Nexus\Contract\Domain\Entities\Escrow;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;

/**
 * Called by HoldEscrowOnContractGeneratedListener reacting to
 * ContractWasGenerated (event-driven, never a direct call from
 * GenerateContractAction). Charges `contract.escrow.hold` (Phase 3/M2's
 * CostGate — 100cr flat, docs/claude/monetization.md's "Payment
 * processing" line) against businessAId (the negotiation's initiator) —
 * same deterministic-initiator simplification GenerateContractOnNegotiationAcceptedListener's
 * own `contract.generate` charge already uses, documented there.
 *
 * The 0.5% half of "Payment processing: 100cr + 0.5%" is the *other*
 * component — not more credits, but the real-money platform_fee snapshot
 * (`platformFeePercent`) Escrow itself carries, read through
 * MarginSettingsService (Phase 3/M5) so an admin's later margin change
 * is reflected immediately, with no restart — never config() directly.
 */
final class HoldEscrowAction
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
        private readonly SpendCreditsForActionAction $costGate,
        private readonly MarginSettingsService $marginSettings,
    ) {
    }

    public function execute(Contract $contract): Escrow
    {
        $terms = $contract->terms();
        $grossAmount = (int) $terms['priceAmount'] * (int) $terms['quantity'];

        $this->costGate->execute($contract->businessAId(), 'contract.escrow.hold', $contract->id());

        $escrow = Escrow::hold(
            contractId: $contract->id(),
            negotiationId: $contract->negotiationId(),
            businessAId: $contract->businessAId(),
            businessBId: $contract->businessBId(),
            grossAmount: $grossAmount,
            currency: $terms['priceCurrency'],
            platformFeePercent: $this->marginSettings->transactionFeePercent(),
        );

        return $this->escrows->save($escrow);
    }
}
