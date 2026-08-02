<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\VariantAttribute;

/**
 * Owns VariantAttributeValue persistence too (save() persists an
 * attribute and every one of its values together) — the same "repo owns
 * its child records" shape WorkflowRepositoryInterface (rules/actions)
 * and TicketRepositoryInterface (comments) already establish.
 */
interface VariantAttributeRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?VariantAttribute;

    public function nameExists(string $name, int $tenantId): bool;

    /**
     * @return list<VariantAttribute>
     */
    public function listByTenant(int $tenantId): array;

    public function save(VariantAttribute $attribute): VariantAttribute;
}
