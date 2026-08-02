<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\ProductVariant as ProductVariantEntity;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;
use App\Modules\Commerce\Infrastructure\Models\ProductVariant as ProductVariantModel;
use DateTimeImmutable;

/**
 * findByProductAndAttributes() compares in PHP over listByProduct()'s
 * already-small result set (bounded by how many variants one Product
 * realistically has) rather than a JSON-column WHERE clause — the same
 * "small N, in-PHP compare" precedent Cart::findItem()'s own linear scan
 * already establishes, simpler than a portable JSON-equality query across
 * SQLite/MySQL.
 */
class EloquentProductVariantRepository implements ProductVariantRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ProductVariantEntity
    {
        $model = ProductVariantModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function skuExists(VariantSKU $sku, int $tenantId): bool
    {
        return ProductVariantModel::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku->value())
            ->exists();
    }

    public function findByProductAndAttributes(int $productId, int $tenantId, array $attributes): ?ProductVariantEntity
    {
        foreach ($this->listByProduct($productId, $tenantId) as $variant) {
            if ($variant->attributes() === $attributes) {
                return $variant;
            }
        }

        return null;
    }

    public function listByProduct(int $productId, int $tenantId): array
    {
        return ProductVariantModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get()
            ->map(fn (ProductVariantModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ProductVariantEntity $variant): ProductVariantEntity
    {
        $model = $variant->id()
            ? ProductVariantModel::query()->where('tenant_id', $variant->tenantId())->findOrFail($variant->id())
            : new ProductVariantModel();

        $model->tenant_id = $variant->tenantId();
        $model->product_id = $variant->productId();
        $model->sku = $variant->sku()->value();
        $model->price_amount = $variant->price()->amount();
        $model->price_currency = $variant->price()->currency();
        $model->attributes = $variant->attributes();
        $model->image_url = $variant->imageUrl();
        $model->is_active = $variant->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id, int $tenantId): void
    {
        ProductVariantModel::query()->where('tenant_id', $tenantId)->where('id', $id)->delete();
    }

    private function toEntity(ProductVariantModel $model): ProductVariantEntity
    {
        return ProductVariantEntity::reconstitute(
            id: $model->id,
            tenantId: $model->tenant_id,
            productId: $model->product_id,
            sku: new VariantSKU($model->sku),
            price: Money::fromAmount($model->price_amount, $model->price_currency),
            attributes: $model->attributes ?? [],
            imageUrl: $model->image_url,
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
