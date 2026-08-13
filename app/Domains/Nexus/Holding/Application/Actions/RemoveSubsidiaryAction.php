<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use InvalidArgumentException;

final class RemoveSubsidiaryAction
{
    public function __construct(
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    public function execute(int $subsidiaryId, int $callingBusinessId): void
    {
        $subsidiary = $this->subsidiaries->findById($subsidiaryId);

        if (! $subsidiary) {
            throw new InvalidArgumentException("Subsidiary [{$subsidiaryId}] does not exist.");
        }

        $holding = $this->holdings->findById($subsidiary->holdingId());

        if (! $holding || $holding->parentBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the Holding\'s administering Business may remove a subsidiary.');
        }

        $subsidiary->remove();

        $this->subsidiaries->save($subsidiary);
    }
}
