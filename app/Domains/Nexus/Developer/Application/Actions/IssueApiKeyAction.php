<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\ApiKeyData;
use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;
use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;

/**
 * Generates the plaintext key exactly once — the same one-time-reveal
 * contract GenerateAgentTokenAction already established for AgentToken.
 * Only the SHA-256 hash (ApiKey::hash(), the AgentToken::hash() pattern
 * reused) is ever persisted; the caller must show `plainKey` to the
 * Business owner immediately and never again.
 */
final class IssueApiKeyAction
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
    ) {
    }

    /**
     * @param list<ApiKeyScope> $scopes
     * @return array{apiKey: ApiKeyData, plainKey: string}
     */
    public function execute(int $businessId, ?string $label, array $scopes): array
    {
        $plainKey = 'nx_'.bin2hex(random_bytes(20));

        $apiKey = ApiKey::issue(
            businessId: $businessId,
            keyHash: ApiKey::hash($plainKey),
            keyPrefix: substr($plainKey, 0, 11),
            label: $label,
            scopes: $scopes,
        );

        $saved = $this->apiKeys->save($apiKey);

        return [
            'apiKey' => ApiKeyData::fromEntity($saved),
            'plainKey' => $plainKey,
        ];
    }
}
