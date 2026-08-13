<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use InvalidArgumentException;

final class RejectSubsidiaryInvitationAction
{
    public function __construct(
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    public function execute(int $subsidiaryId, int $callingBusinessId): void
    {
        $subsidiary = $this->subsidiaries->findById($subsidiaryId);

        if (! $subsidiary) {
            throw new InvalidArgumentException("Subsidiary invitation [{$subsidiaryId}] does not exist.");
        }

        if ($subsidiary->businessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the invited Business may reject its own invitation.');
        }

        $subsidiary->remove();

        $this->subsidiaries->save($subsidiary);
    }
}
