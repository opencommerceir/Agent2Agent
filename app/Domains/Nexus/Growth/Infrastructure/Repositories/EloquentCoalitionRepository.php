<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\Coalition as CoalitionEntity;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use App\Domains\Nexus\Growth\Infrastructure\Models\Coalition as CoalitionModel;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use DateTimeImmutable;

class EloquentCoalitionRepository implements CoalitionRepositoryInterface
{
    public function findById(int $id): ?CoalitionEntity
    {
        $model = CoalitionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStatus(string $status): array
    {
        return CoalitionModel::query()
            ->where('status', $status)
            ->orderByDesc('id')
            ->get()
            ->map(fn (CoalitionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByNegotiationId(int $negotiationId): ?CoalitionEntity
    {
        $model = CoalitionModel::query()->where('negotiation_id', $negotiationId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(CoalitionEntity $coalition): CoalitionEntity
    {
        $model = $coalition->id()
            ? CoalitionModel::query()->findOrFail($coalition->id())
            : new CoalitionModel();

        $model->organizer_business_id = $coalition->organizerBusinessId();
        $model->target_business_id = $coalition->targetBusinessId();
        $model->catalog_item_type = $coalition->catalogItemType()->value;
        $model->catalog_item_id = $coalition->catalogItemId();
        $model->unit_price_amount = $coalition->unitPrice()->amount();
        $model->unit_price_currency = $coalition->unitPrice()->currency();
        $model->min_participants = $coalition->minParticipants();
        $model->discount_percent = $coalition->discountPercent();
        $model->status = $coalition->status()->value;
        $model->negotiation_id = $coalition->negotiationId();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CoalitionModel $model): CoalitionEntity
    {
        return new CoalitionEntity(
            id: $model->id,
            organizerBusinessId: $model->organizer_business_id,
            targetBusinessId: $model->target_business_id,
            catalogItemType: CatalogItemType::from($model->catalog_item_type),
            catalogItemId: $model->catalog_item_id,
            unitPrice: Money::fromAmount($model->unit_price_amount, $model->unit_price_currency),
            minParticipants: $model->min_participants,
            discountPercent: (float) $model->discount_percent,
            status: CoalitionStatus::from($model->status),
            negotiationId: $model->negotiation_id,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
