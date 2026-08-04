<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\DiscountRule;

/**
 * Owns DiscountRuleCondition persistence too (frozen at creation, never
 * looked up independently) — the same "repo owns its child records" shape
 * `WarehouseTransferRepositoryInterface` already establishes for
 * `WarehouseTransferItem` (§7.22).
 */
interface DiscountRuleRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?DiscountRule;

    /**
     * @return list<DiscountRule>
     */
    public function listByTenant(int $tenantId, ?bool $isActive = null): array;

    public function save(DiscountRule $rule): DiscountRule;

    public function delete(int $id, int $tenantId): void;
}
