<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Organization;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): ?Organization;

    public function findBySlug(int $tenantId, string $slug): ?Organization;

    public function existsBySlug(int $tenantId, string $slug): bool;

    public function save(Organization $organization): Organization;
}
