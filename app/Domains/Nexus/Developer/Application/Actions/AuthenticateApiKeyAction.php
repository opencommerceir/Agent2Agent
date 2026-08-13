<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;
use App\Domains\Nexus\Developer\Domain\Exceptions\InvalidApiKeyException;
use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;

/**
 * The Public REST API's (Phase 9/M2) verification entry point — the
 * ApiKey-scoped counterpart to Core's AuthenticateAgentAction. Looks the
 * key up by hash (never by plaintext), rejects revoked/expired keys with
 * the same single exception type regardless of which reason applies (see
 * InvalidApiKeyException's own docblock), and records usage on success.
 */
final class AuthenticateApiKeyAction
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
    ) {
    }

    public function execute(string $plainKey): ApiKey
    {
        $apiKey = $this->apiKeys->findByHash(ApiKey::hash($plainKey));

        if (! $apiKey || ! $apiKey->isValid()) {
            throw new InvalidApiKeyException('Invalid or expired API key.');
        }

        $apiKey->markUsed();
        $this->apiKeys->save($apiKey);

        return $apiKey;
    }
}
