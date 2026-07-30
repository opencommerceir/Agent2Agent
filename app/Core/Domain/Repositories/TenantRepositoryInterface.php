<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Tenant;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 */
interface TenantRepositoryInterface
{
    public function findById(int $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function slugExists(string $slug): bool;

    public function save(Tenant $tenant): Tenant;
}
