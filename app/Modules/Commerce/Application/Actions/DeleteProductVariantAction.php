<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Events\VariantWasDeleted;
use App\Modules\Commerce\Domain\Exceptions\VariantNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Soft-deletes a ProductVariant (ProductVariantRepositoryInterface::delete()
 * — EloquentProductVariantRepository relies on the `product_variants`
 * table's SoftDeletes column, mirroring DeleteProductAction exactly) —
 * preserves referential integrity for any historical Order that already
 * references this variant.
 */
final class DeleteProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $variants,
    ) {
    }

    public function execute(int $id, int $tenantId): void
    {
        $variant = $this->variants->findById($id, $tenantId);

        if (! $variant) {
            throw new VariantNotFoundException("Variant [{$id}] does not exist.");
        }

        $this->variants->delete($id, $tenantId);

        Event::dispatch(new VariantWasDeleted($id, $variant->productId(), $tenantId));
    }
}
