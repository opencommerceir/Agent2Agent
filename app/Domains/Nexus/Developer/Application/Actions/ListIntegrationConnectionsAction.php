<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\IntegrationConnectionData;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;

final class ListIntegrationConnectionsAction
{
    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connections,
    ) {
    }

    /**
     * @return list<IntegrationConnectionData>
     */
    public function execute(int $businessId): array
    {
        return array_values(array_map(
            fn ($connection) => IntegrationConnectionData::fromEntity($connection),
            $this->connections->findByBusinessId($businessId),
        ));
    }
}
