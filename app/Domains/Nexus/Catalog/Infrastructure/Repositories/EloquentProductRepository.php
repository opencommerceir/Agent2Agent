<?php

namespace App\Domains\Nexus\Catalog\Infrastructure\Repositories;

use App\Domains\Nexus\Catalog\Domain\Entities\Product as ProductEntity;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Product as ProductModel;
use DateTimeImmutable;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?ProductEntity
    {
        $model = ProductModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return ProductModel::query()
            ->where('business_id', $businessId)
            ->get()
            ->map(fn (ProductModel $model) => $this->toEntity($model))
            ->all();
    }

    public function search(int $businessId, string $query): array
    {
        return ProductModel::query()
            ->where('business_id', $businessId)
            ->where(fn ($q) => $q->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%"))
            ->get()
            ->map(fn (ProductModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByVerificationStatus(ListingVerificationStatus $status): array
    {
        return ProductModel::query()
            ->where('verification_status', $status->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProductModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ProductEntity $product): ProductEntity
    {
        $model = $product->id()
            ? ProductModel::query()->findOrFail($product->id())
            : new ProductModel();

        $model->business_id = $product->businessId();
        $model->name_fa = $product->nameFa();
        $model->name_en = $product->nameEn();
        $model->price_amount = $product->price()->amount();
        $model->price_currency = $product->price()->currency();
        $model->stock_quantity = $product->stockQuantity();
        $model->attributes = $product->attributes();
        $model->verification_status = $product->verificationStatus()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ProductModel $model): ProductEntity
    {
        return new ProductEntity(
            id: $model->id,
            businessId: $model->business_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            price: Money::fromAmount($model->price_amount, $model->price_currency),
            stockQuantity: $model->stock_quantity,
            attributes: $model->attributes,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            verificationStatus: ListingVerificationStatus::from($model->verification_status),
        );
    }
}
