<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Events\ProductWasDeleted;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Soft-deletes a Product (ProductRepositoryInterface::delete() —
 * EloquentProductRepository relies on the `products` table's SoftDeletes
 * column rather than removing the row).
 */
final class DeleteProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $id, int $tenantId): void
    {
        if (! $this->products->findById($id, $tenantId)) {
            throw new ProductNotFoundException("Product [{$id}] does not exist.");
        }

        $this->products->delete($id, $tenantId);

        Event::dispatch(new ProductWasDeleted($id, $tenantId));
    }
}
