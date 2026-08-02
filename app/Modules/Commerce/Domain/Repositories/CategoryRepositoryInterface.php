<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Category;

    /**
     * Added for Bulk Operations (Phase 5, Stage 3, §7.23) —
     * `ImportProductsAction`'s own CSV shape names a `category` column by
     * *name*, not id (rule §ه's own example: `category,status`), and
     * nothing before this stage ever needed to resolve a Category any way
     * other than by its own id. Case-sensitive exact match; an unresolved
     * name is left as `categoryId: null` on the imported Product rather
     * than failing the whole row (a category is enrichment, not a
     * required field — `CreateProductAction`'s own `categoryId` has
     * always been optional).
     */
    public function findByName(string $name, int $tenantId): ?Category;

    public function slugExists(string $slug, int $tenantId): bool;

    public function save(Category $category): Category;
}
