<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CategoryData;
use App\Modules\Commerce\Domain\Entities\Category;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One Action = one business operation (Application Layer Rules):
 * create a Category with an auto-generated slug (Commerce's explicit
 * requirement — unlike Tenant/Organization, whose slugs are supplied by
 * the caller).
 */
final class CreateCategoryAction
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function execute(int $tenantId, string $name, ?string $description = null): CategoryData
    {
        $slug = Str::slug($name);

        if ($this->categories->slugExists($slug, $tenantId)) {
            throw new InvalidArgumentException("Category slug [{$slug}] is already taken in this tenant.");
        }

        $category = Category::create($tenantId, $name, $slug, $description);
        $category = $this->categories->save($category);

        return CategoryData::fromEntity($category);
    }
}
