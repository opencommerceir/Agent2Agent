<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\ApiKeyData;
use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;

final class ListApiKeysAction
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
    ) {
    }

    /**
     * @return list<ApiKeyData>
     */
    public function execute(int $businessId): array
    {
        return array_values(array_map(
            fn ($apiKey) => ApiKeyData::fromEntity($apiKey),
            $this->apiKeys->findByBusinessId($businessId),
        ));
    }
}
