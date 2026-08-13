<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\IntegrationConnectionData;
use App\Domains\Nexus\Developer\Domain\Entities\IntegrationConnection;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;

final class CreateIntegrationConnectionAction
{
    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connections,
    ) {
    }

    /**
     * @param array<string, string> $fieldMapping
     */
    public function execute(
        int $businessId,
        IntegrationCategory $category,
        string $name,
        string $targetUrl,
        ?string $authToken,
        array $fieldMapping,
    ): IntegrationConnectionData {
        $connection = IntegrationConnection::create($businessId, $category, $name, $targetUrl, $authToken, $fieldMapping);

        return IntegrationConnectionData::fromEntity($this->connections->save($connection));
    }
}
