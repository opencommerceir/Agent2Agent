<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use InvalidArgumentException;

final class SetCreditPoolingEnabledAction
{
    public function __construct(
        private readonly HoldingRepositoryInterface $holdings,
    ) {
    }

    public function execute(int $holdingId, int $callingBusinessId, bool $enabled): void
    {
        $holding = $this->holdings->findById($holdingId);

        if (! $holding) {
            throw new InvalidArgumentException("Holding [{$holdingId}] does not exist.");
        }

        if ($holding->parentBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the Holding\'s administering Business may toggle credit pooling.');
        }

        if ($enabled) {
            $holding->enableCreditPooling();
        } else {
            $holding->disableCreditPooling();
        }

        $this->holdings->save($holding);
    }
}
