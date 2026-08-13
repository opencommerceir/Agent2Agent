<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;
use InvalidArgumentException;

final class RevokeApiKeyAction
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
    ) {
    }

    public function execute(int $apiKeyId, int $actingBusinessId): void
    {
        $apiKey = $this->apiKeys->findById($apiKeyId);

        if (! $apiKey || $apiKey->businessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("ApiKey [{$apiKeyId}] does not belong to Business [{$actingBusinessId}].");
        }

        $apiKey->revoke();

        $this->apiKeys->save($apiKey);
    }
}
