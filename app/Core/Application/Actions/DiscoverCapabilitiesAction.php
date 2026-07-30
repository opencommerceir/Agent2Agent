<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\CapabilityData;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;

/**
 * Backs the MCP discovery endpoint (GET /mcp/v1/capabilities): lists every
 * capability that exists on the platform, regardless of which permissions
 * the calling Agent happens to hold. Discovery is documentation — like an
 * OpenAPI spec listing every endpoint whether or not the caller's API key
 * can reach all of them — actual authorization is enforced separately, at
 * execution time, by CheckPermissionAction.
 */
final class DiscoverCapabilitiesAction
{
    public function __construct(
        private readonly CapabilityRepositoryInterface $capabilities,
    ) {
    }

    /**
     * @return list<CapabilityData>
     */
    public function execute(): array
    {
        return array_map(
            fn ($capability) => CapabilityData::fromEntity($capability),
            $this->capabilities->all(),
        );
    }
}
