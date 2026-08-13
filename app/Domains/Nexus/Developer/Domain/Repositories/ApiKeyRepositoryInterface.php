<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 */
interface ApiKeyRepositoryInterface
{
    public function findById(int $id): ?ApiKey;

    public function findByHash(string $keyHash): ?ApiKey;

    /**
     * @return list<ApiKey>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(ApiKey $apiKey): ApiKey;
}
