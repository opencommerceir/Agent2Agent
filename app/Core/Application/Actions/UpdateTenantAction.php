<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\TenantData;
use App\Core\Domain\Exceptions\TenantNotFoundException;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\TenantStatus;

/**
 * Not named in Phase 4 Stage 5's own request, but its "Edit Tenant"
 * Dashboard page implies it — no Action existed anywhere to change an
 * existing Tenant's name/status at all before this (`Tenant::activate()`/
 * `suspend()` have existed since Phase 1 with zero callers, the same
 * "mutator with no Action wired to it yet" gap Cart::abandon() had before
 * the Tech Debt Sprint, HANDOFF §7.13). Slug is deliberately not editable
 * here, the same "identifier immutable after creation" shape Product's
 * SKU/Category's slug already have.
 */
final class UpdateTenantAction
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function execute(int $id, string $name, string $status): TenantData
    {
        $tenant = $this->tenants->findById($id);

        if (! $tenant) {
            throw new TenantNotFoundException("Tenant [{$id}] does not exist.");
        }

        $tenant->rename($name);

        match (TenantStatus::from($status)) {
            TenantStatus::Active => $tenant->activate(),
            TenantStatus::Suspended => $tenant->suspend(),
            TenantStatus::Pending => null,
        };

        $tenant = $this->tenants->save($tenant);

        return TenantData::fromEntity($tenant);
    }
}
