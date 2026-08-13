<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\IntegrationConnection;

interface IntegrationConnectionRepositoryInterface
{
    public function findById(int $id): ?IntegrationConnection;

    /**
     * @return list<IntegrationConnection>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(IntegrationConnection $connection): IntegrationConnection;
}
