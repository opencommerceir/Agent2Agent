<?php

namespace App\Domains\Nexus\Catalog\Domain\Repositories;

use App\Domains\Nexus\Catalog\Domain\Entities\Service;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;

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

    /**
     * @return list<Service>
     */
    public function findByVerificationStatus(ListingVerificationStatus $status): array;

    public function save(Service $service): Service;
}
