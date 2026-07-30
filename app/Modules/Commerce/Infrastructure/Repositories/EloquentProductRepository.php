<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Product as ProductEntity;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use App\Modules\Commerce\Infrastructure\Models\Product as ProductModel;
use DateTimeImmutable;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ProductEntity
    {
        $model = ProductModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySku(SKU $sku, int $tenantId): ?ProductEntity
    {
        $model = ProductModel::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku->value())
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function skuExists(SKU $sku, int $tenantId): bool
    {
        return ProductModel::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku->value())
            ->exists();
    }

    public function search(int $tenantId, ?string $query, ?ProductStatus $status, int $limit, int $offset): array
    {
        $builder = ProductModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($query !== null && $query !== '') {
            $builder->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            });
        }

        return $builder->orderBy('id')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn (ProductModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ProductEntity $product): ProductEntity
    {
        $model = $product->id()
            ? ProductModel::query()->where('tenant_id', $product->tenantId())->findOrFail($product->id())
            : new ProductModel();

        $model->tenant_id = $product->tenantId();
        $model->category_id = $product->categoryId();
        $model->name = $product->name();
        $model->slug = $product->slug();
        $model->description = $product->description();
        $model->sku = $product->sku()->value();
        $model->price_amount = $product->price()->amount();
        $model->price_currency = $product->price()->currency();
        $model->status = $product->status()->value;
        $model->attributes = $product->attributes();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id, int $tenantId): void
    {
        ProductModel::query()->where('tenant_id', $tenantId)->where('id', $id)->delete();
    }

    private function toEntity(ProductModel $model): ProductEntity
    {
        return new ProductEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            categoryId: $model->category_id,
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            sku: new SKU($model->sku),
            price: Money::fromAmount($model->price_amount, $model->price_currency),
            status: ProductStatus::from($model->status),
            attributes: $model->attributes ?? [],
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
