<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Category;

    public function slugExists(string $slug, int $tenantId): bool;

    public function save(Category $category): Category;
}
