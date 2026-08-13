<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use InvalidArgumentException;

final class RevokeIntegrationConnectionAction
{
    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connections,
    ) {
    }

    public function execute(int $connectionId, int $actingBusinessId): void
    {
        $connection = $this->connections->findById($connectionId);

        if (! $connection || $connection->businessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("IntegrationConnection [{$connectionId}] does not belong to Business [{$actingBusinessId}].");
        }

        $connection->revoke();

        $this->connections->save($connection);
    }
}
