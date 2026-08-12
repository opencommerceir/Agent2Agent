<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\DisputeCaseData;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use InvalidArgumentException;

/**
 * Either party to the underlying Escrow can add a text note — self-
 * contained authorization via DisputeCase::isParty() (delegated to
 * addEvidence() itself, same "entity enforces its own invariant" shape
 * Negotiation::counter()'s round-limit check already uses).
 */
final class SubmitDisputeEvidenceAction
{
    public function __construct(
        private readonly DisputeCaseRepositoryInterface $disputeCases,
    ) {
    }

    public function execute(int $disputeCaseId, int $actingBusinessId, string $note): DisputeCaseData
    {
        $disputeCase = $this->disputeCases->findById($disputeCaseId);

        if (! $disputeCase) {
            throw new InvalidArgumentException("DisputeCase [{$disputeCaseId}] does not exist.");
        }

        $disputeCase->addEvidence($actingBusinessId, $note);

        return DisputeCaseData::fromEntity($this->disputeCases->save($disputeCase));
    }
}
