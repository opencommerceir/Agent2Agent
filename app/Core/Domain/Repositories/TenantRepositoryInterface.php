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

    /**
     * Added for the scheduler mechanism (HANDOFF §8.23/§8.27): any
     * cross-tenant scheduled job (loyalty point expiration, abandoned-cart
     * detection) needs to enumerate every Tenant to iterate over — nothing
     * before this needed to list Tenants in bulk. Small and generically
     * useful for any future batch job, not scoped to just these two.
     *
     * @return list<Tenant>
     */
    public function all(): array;

    public function save(Tenant $tenant): Tenant;
}
