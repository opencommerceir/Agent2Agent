<?php

namespace App\Domains\Nexus\Catalog\Domain\Repositories;

use App\Domains\Nexus\Catalog\Domain\Entities\Service;

interface ServiceRepositoryInterface
{
    public function findById(int $id): ?Service;

    /**
     * @return list<Service>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return list<Service>
     */
    public function search(int $businessId, string $query): array;

    public function save(Service $service): Service;
}
