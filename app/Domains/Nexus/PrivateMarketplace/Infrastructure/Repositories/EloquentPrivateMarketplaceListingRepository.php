<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceListing as PrivateMarketplaceListingEntity;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceListingRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplaceListing as PrivateMarketplaceListingModel;
use DateTimeImmutable;

class EloquentPrivateMarketplaceListingRepository implements PrivateMarketplaceListingRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplaceListingEntity
    {
        $model = PrivateMarketplaceListingModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByPrivateMarketplaceId(int $privateMarketplaceId): array
    {
        return PrivateMarketplaceListingModel::query()
            ->where('private_marketplace_id', $privateMarketplaceId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PrivateMarketplaceListingModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(PrivateMarketplaceListingEntity $listing): PrivateMarketplaceListingEntity
    {
        $model = $listing->id()
            ? PrivateMarketplaceListingModel::query()->findOrFail($listing->id())
            : new PrivateMarketplaceListingModel();

        $model->private_marketplace_id = $listing->privateMarketplaceId();
        $model->listing_business_id = $listing->listingBusinessId();
        $model->catalog_item_type = $listing->catalogItemType()->value;
        $model->catalog_item_id = $listing->catalogItemId();
        $model->special_price_amount = $listing->specialPrice()->amount();
        $model->special_price_currency = $listing->specialPrice()->currency();
        $model->created_at = $listing->createdAt();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): void
    {
        PrivateMarketplaceListingModel::query()->where('id', $id)->delete();
    }

    private function toEntity(PrivateMarketplaceListingModel $model): PrivateMarketplaceListingEntity
    {
        return new PrivateMarketplaceListingEntity(
            id: $model->id,
            privateMarketplaceId: $model->private_marketplace_id,
            listingBusinessId: $model->listing_business_id,
            catalogItemType: CatalogItemType::from($model->catalog_item_type),
            catalogItemId: $model->catalog_item_id,
            specialPrice: Money::fromAmount($model->special_price_amount, $model->special_price_currency),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
