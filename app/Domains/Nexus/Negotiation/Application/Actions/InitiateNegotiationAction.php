<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService;
use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use InvalidArgumentException;

/**
 * Opens a Negotiation between two different Businesses over one catalog
 * item — the "Proposal" step of the roadmap's Discovery -> Match ->
 * Proposal -> Counter -> Agree flow. $initiatorBusinessId/$counterpartyBusinessId
 * come from the caller (typically an MCP handler resolving the calling
 * Business via ResolveActingBusinessAction, M2) — this Action has no
 * opinion on how they were discovered (Marketplace's own job).
 */
final class InitiateNegotiationAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly NegotiationReasoningService $reasoning,
    ) {
    }

    public function execute(
        int $initiatorBusinessId,
        int $counterpartyBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        NegotiationTerms $terms,
        ?int $maxRounds = null,
    ): NegotiationData {
        if ($initiatorBusinessId === $counterpartyBusinessId) {
            throw new InvalidArgumentException('A Business cannot negotiate with itself.');
        }

        $initiator = $this->businesses->findById($initiatorBusinessId);
        $counterparty = $this->businesses->findById($counterpartyBusinessId);

        if (! $initiator || ! $counterparty) {
            throw new InvalidArgumentException('Both the initiator and counterparty Business must exist.');
        }

        $negotiation = Negotiation::propose(
            initiatorBusinessId: $initiatorBusinessId,
            initiatorTenantId: $initiator->tenantId(),
            counterpartyBusinessId: $counterpartyBusinessId,
            counterpartyTenantId: $counterparty->tenantId(),
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            terms: $terms,
            maxRounds: $maxRounds ?? (int) config('nexus.platform.negotiation.max_rounds'),
        );
        $negotiation = $this->negotiations->save($negotiation);

        $this->messages->save(NegotiationMessage::record(
            negotiationId: $negotiation->id(),
            senderBusinessId: $initiatorBusinessId,
            type: NegotiationMessageType::Proposal,
            terms: $terms,
            reasoning: $this->reasoning->forProposal($terms),
        ));

        return NegotiationData::fromEntity($negotiation);
    }
}
