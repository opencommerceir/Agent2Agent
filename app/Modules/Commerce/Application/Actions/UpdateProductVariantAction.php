<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Events\VariantWasUpdated;
use App\Modules\Commerce\Domain\Exceptions\VariantNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\Event;

/**
 * SKU/attributes are deliberately not updatable here — ProductVariant's
 * own docblock explains why (mirrors Product's own SKU-is-immutable
 * rule). `stockQuantity`, when given, goes through
 * Inventory::setQuantityOnHand() — the one direct administrative
 * override that entity has (see its own docblock, §7.21) — never
 * reserve()/commit(), since this isn't a Cart/Order event, it's a
 * provisioning/correction operation.
 */
final class UpdateProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(
        int $id,
        int $tenantId,
        int $priceAmount,
        string $priceCurrency,
        ?string $imageUrl,
        bool $isActive,
        ?int $stockQuantity = null,
    ): ProductVariantData {
        $variant = $this->variants->findById($id, $tenantId);

        if (! $variant) {
            throw new VariantNotFoundException("Variant [{$id}] does not exist.");
        }

        $variant->update(Money::fromAmount($priceAmount, $priceCurrency), $imageUrl, $isActive);
        $variant = $this->variants->save($variant);

        $inventory = $this->inventories->findByProduct($variant->productId(), $tenantId, $variant->id());

        if ($stockQuantity !== null) {
            $inventory ??= Inventory::stock($tenantId, $variant->productId(), 0, $variant->id());
            $inventory->setQuantityOnHand($stockQuantity);
            $inventory = $this->inventories->save($inventory);
        }

        Event::dispatch(new VariantWasUpdated($variant));

        return ProductVariantData::fromEntity($variant, $inventory);
    }
}
