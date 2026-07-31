<?php

namespace App\Modules\CRM\Domain\Repositories;

use App\Modules\CRM\Domain\Entities\Tag;
use App\Modules\CRM\Domain\ValueObjects\TagName;

/**
 * Owns the customer_tag pivot too (assignToCustomer()) — a plain
 * many-to-many join with no fields of its own and no independent
 * meaning outside "this Tag is assigned to this Customer", the same
 * "no separate repository/entity for a bare join row" reasoning
 * Discount/OrderItem already established for having no `id` field.
 */
interface TagRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Tag;

    public function nameExists(TagName $name, int $tenantId): bool;

    public function save(Tag $tag): Tag;

    public function assignToCustomer(int $tagId, int $customerId): void;
}
